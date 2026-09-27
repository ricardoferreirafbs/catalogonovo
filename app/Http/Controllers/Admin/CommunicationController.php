<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\CommunicationRecipient;
use App\Services\CommunicationPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $recipients = CommunicationRecipient::query()
            ->where('user_id', $request->user()->id)
            ->with(['communication' => fn ($query) => $query->with('tenant:id,name')->withCount('messages')])
            ->whereHas('communication', fn ($query) => $query->whereIn('status', ['published', 'closed']))
            ->latest('id')
            ->paginate(25);

        return view('admin.communications.index', compact('recipients'));
    }

    public function show(Request $request, Communication $communication): View
    {
        $recipient = $this->recipient($request, $communication);
        if (! $recipient->read_at) {
            $recipient->update(['read_at' => now()]);
        }
        $communication->load(['messages.sender:id,name', 'tenant:id,name']);

        return view('admin.communications.show', compact('communication', 'recipient'));
    }

    public function reply(Request $request, Communication $communication, CommunicationPublisher $publisher): RedirectResponse
    {
        $this->recipient($request, $communication);
        abort_if($communication->status !== 'published', 409, 'Esta comunicação está encerrada.');
        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);

        DB::transaction(function () use ($communication, $request, $data): void {
            $communication->messages()->create([
                'sender_user_id' => $request->user()->id,
                'sender_side' => 'tenant',
                'body' => $data['body'],
            ]);
            $communication->update(['expires_at' => now()->addDays(max(1, (int) config('communication.retention_days', 60)))]);
        });

        $failures = $publisher->notifyPlatform($communication);

        return back()->with($failures ? 'warning' : 'success', $failures
            ? 'Resposta salva, mas o aviso por e-mail à plataforma falhou.'
            : 'Resposta enviada com segurança.');
    }

    public function acknowledge(Request $request, Communication $communication): RedirectResponse
    {
        $recipient = $this->recipient($request, $communication);
        abort_unless($communication->requires_acknowledgement, 409, 'Esta comunicação não exige confirmação.');
        DB::transaction(function () use ($recipient, $communication): void {
            $recipient->update([
                'read_at' => $recipient->read_at ?? now(),
                'acknowledged_at' => $recipient->acknowledged_at ?? now(),
            ]);
            $communication->update([
                'expires_at' => now()->addDays(max(1, (int) config('communication.retention_days', 60))),
            ]);
        });

        return back()->with('success', 'Ciência registrada com data, horário e usuário responsável.');
    }

    private function recipient(Request $request, Communication $communication): CommunicationRecipient
    {
        abort_unless($communication->tenant_id === $request->user()->tenant_id, 404);
        abort_unless(in_array($request->user()->role, $communication->recipient_roles, true), 404);

        return CommunicationRecipient::query()
            ->where('communication_id', $communication->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
