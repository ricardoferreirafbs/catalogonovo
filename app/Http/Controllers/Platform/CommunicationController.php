<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommunicationPublisher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status');
        $category = (string) $request->query('category');
        $tenantId = $request->integer('tenant_id') ?: null;

        $communications = Communication::query()
            ->with('tenant:id,name')
            ->withCount('recipients')
            ->when(array_key_exists($status, Communication::STATUS_LABELS), fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($category, Communication::CATEGORY_LABELS), fn ($query) => $query->where('category', $category))
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $tenants = Tenant::query()->orderBy('name')->get(['id', 'name']);

        return view('platform.communications.index', compact('communications', 'tenants', 'status', 'category', 'tenantId'));
    }

    public function create(): View
    {
        $tenants = Tenant::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('platform.communications.create', compact('tenants'));
    }

    public function store(Request $request, CommunicationPublisher $publisher): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id', 'required_without:send_to_all'],
            'send_to_all' => ['sometimes', 'accepted'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:8000'],
            'category' => ['required', Rule::in(array_keys(Communication::CATEGORY_LABELS))],
            'priority' => ['required', Rule::in(array_keys(Communication::PRIORITY_LABELS))],
            'recipient_roles' => ['required', 'array', 'min:1'],
            'recipient_roles.*' => ['required', Rule::in(array_keys(User::TENANT_ROLE_LABELS))],
            'requires_acknowledgement' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        $scheduledAt = $this->parseSchedule($data['scheduled_at'] ?? null);
        $tenants = ! empty($data['send_to_all'])
            ? Tenant::query()->where('status', 'active')->get()
            : Tenant::query()->whereKey($data['tenant_id'])->where('status', 'active')->get();

        if ($tenants->isEmpty()) {
            throw ValidationException::withMessages(['tenant_id' => 'Selecione ao menos uma empresa ativa.']);
        }

        $communications = DB::transaction(function () use ($data, $scheduledAt, $tenants, $request) {
            return $tenants->map(function (Tenant $tenant) use ($data, $scheduledAt, $request): Communication {
                $communication = Communication::create([
                    'protocol' => $this->newProtocol(),
                    'tenant_id' => $tenant->id,
                    'created_by_user_id' => $request->user()->id,
                    'subject' => $data['subject'],
                    'category' => $data['category'],
                    'priority' => $data['priority'],
                    'status' => $scheduledAt ? 'scheduled' : 'draft',
                    'recipient_roles' => array_values(array_unique($data['recipient_roles'])),
                    'requires_acknowledgement' => $request->boolean('requires_acknowledgement'),
                    'scheduled_at' => $scheduledAt,
                ]);
                $communication->messages()->create([
                    'sender_user_id' => $request->user()->id,
                    'sender_side' => 'platform',
                    'body' => $data['body'],
                ]);

                return $communication;
            });
        });

        $failures = 0;
        if (! $scheduledAt) {
            foreach ($communications as $communication) {
                $failures += $publisher->publish($communication);
            }
        }

        $message = $scheduledAt
            ? $communications->count().' comunicação(ões) agendada(s).'
            : $communications->count().' comunicação(ões) publicada(s).';

        return redirect()->route('platform.communications.index')->with(
            $failures > 0 ? 'warning' : 'success',
            $message.($failures > 0 ? " {$failures} aviso(s) por e-mail falharam; o conteúdo permanece disponível no painel." : '')
        );
    }

    public function show(Communication $communication): View
    {
        $communication->load(['tenant:id,name', 'creator:id,name', 'messages.sender:id,name', 'recipients.user:id,name,email,role']);

        return view('platform.communications.show', compact('communication'));
    }

    public function reply(Request $request, Communication $communication, CommunicationPublisher $publisher): RedirectResponse
    {
        abort_if($communication->status !== 'published', 409, 'A comunicação não está aberta para respostas.');
        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);

        DB::transaction(function () use ($communication, $request, $data): void {
            $communication->messages()->create([
                'sender_user_id' => $request->user()->id,
                'sender_side' => 'platform',
                'body' => $data['body'],
            ]);
            $communication->update(['expires_at' => now()->addDays(max(1, (int) config('communication.retention_days', 60)))]);
        });

        $failures = $publisher->notifyTenantRecipients($communication);

        return back()->with($failures ? 'warning' : 'success', $failures
            ? 'Resposta salva, mas um ou mais avisos por e-mail falharam.'
            : 'Resposta enviada aos destinatários.');
    }

    public function close(Communication $communication): RedirectResponse
    {
        abort_if($communication->status !== 'published', 409, 'Somente comunicações publicadas podem ser encerradas.');
        $communication->update([
            'status' => 'closed',
            'closed_at' => now(),
            'expires_at' => now()->addDays(max(1, (int) config('communication.retention_days', 60))),
        ]);

        return back()->with('success', 'Comunicação encerrada. A exclusão ocorrerá após o prazo de retenção.');
    }

    private function parseSchedule(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $scheduledAt = Carbon::createFromFormat('Y-m-d\TH:i', $value, config('app.display_timezone'))->utc();
        if ($scheduledAt->lte(now())) {
            throw ValidationException::withMessages(['scheduled_at' => 'O agendamento precisa estar no futuro.']);
        }

        return $scheduledAt;
    }

    private function newProtocol(): string
    {
        do {
            $protocol = 'COM-'.Str::upper(Str::random(12));
        } while (Communication::where('protocol', $protocol)->exists());

        return $protocol;
    }
}
