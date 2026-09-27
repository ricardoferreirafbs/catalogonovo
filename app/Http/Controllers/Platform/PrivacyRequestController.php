<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PrivacyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PrivacyRequestController extends Controller
{
    public function index(Request $request): View
    {
        $search = Str::upper(trim((string) $request->query('q')));
        $status = (string) $request->query('status');
        $type = (string) $request->query('type');

        $requests = PrivacyRequest::query()
            ->with('reviewer:id,name')
            ->when($search, fn ($query) => $query->where('protocol', 'like', "%{$search}%"))
            ->when(array_key_exists($status, PrivacyRequest::STATUS_LABELS), fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($type, PrivacyRequest::TYPE_LABELS), fn ($query) => $query->where('request_type', $type))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $metrics = [
            'verified' => PrivacyRequest::where('status', 'verified')->count(),
            'in_review' => PrivacyRequest::where('status', 'in_review')->count(),
            'completed' => PrivacyRequest::where('status', 'completed')->count(),
            'awaiting' => PrivacyRequest::where('status', 'awaiting_verification')->count(),
        ];

        $this->auditAccess($request, 'platform.privacy.index');

        return view('platform.privacy.index', compact('requests', 'metrics', 'search', 'status', 'type'));
    }

    public function show(Request $request, PrivacyRequest $privacyRequest): View
    {
        $privacyRequest->load('reviewer:id,name,email');
        $this->auditAccess($request, 'platform.privacy.show', $privacyRequest);

        return view('platform.privacy.show', compact('privacyRequest'));
    }

    public function update(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PrivacyRequest::STATUS_LABELS))],
            'internal_notes' => [
                Rule::requiredIf(in_array($request->input('status'), ['completed', 'rejected'], true)),
                'nullable', 'string', 'max:6000',
            ],
        ], [
            'internal_notes.required' => 'Registre a decisão e as providências antes de concluir ou não atender a solicitação.',
        ]);

        if (! $privacyRequest->email_verified_at && $data['status'] !== 'awaiting_verification') {
            return back()->withErrors([
                'status' => 'A solicitação não pode avançar antes da confirmação do endereço de e-mail.',
            ])->withInput();
        }

        if ($privacyRequest->email_verified_at && $data['status'] === 'awaiting_verification') {
            return back()->withErrors([
                'status' => 'O endereço já foi confirmado e a solicitação não pode voltar para a etapa anterior.',
            ])->withInput();
        }

        $completed = in_array($data['status'], ['completed', 'rejected'], true);

        $privacyRequest->update([
            'status' => $data['status'],
            'internal_notes' => $data['internal_notes'] ?? null,
            'reviewed_by_user_id' => $request->user()->id,
            'completed_at' => $completed ? ($privacyRequest->completed_at ?? now()) : null,
            'retention_until' => $completed
                ? now()->addDays(max(90, (int) config('privacy.request_retention_days', 730)))
                : $privacyRequest->retention_until,
        ]);

        return back()->with('success', 'Solicitação de privacidade atualizada.');
    }

    private function auditAccess(Request $request, string $event, ?PrivacyRequest $privacyRequest = null): void
    {
        try {
            AuditLog::create([
                'actor_user_id' => $request->user()?->id,
                'tenant_id' => null,
                'event' => $event,
                'method' => $request->method(),
                'path' => Str::limit($request->path(), 500, ''),
                'status' => 200,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => ['outcome' => 'success', 'privacy_protocol' => $privacyRequest?->protocol],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Não foi possível auditar a consulta de privacidade.', [
                'event' => $event,
                'exception' => $exception::class,
            ]);
        }
    }
}
