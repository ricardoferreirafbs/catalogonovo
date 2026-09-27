<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ErrorOccurrence;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ErrorOccurrenceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $classification = (string) $request->query('classification');
        $tenantId = $request->integer('tenant_id') ?: null;

        $occurrences = ErrorOccurrence::query()
            ->with(['tenant:id,name', 'actor:id,name,email'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('protocol', 'like', "%{$search}%")
                    ->orWhere('error_code', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn ($query) => $query->where('email', 'like', "%{$search}%"));
            }))
            ->when(array_key_exists($status, ErrorOccurrence::STATUS_LABELS), fn ($query) => $query->where('status', $status))
            ->when($classification === 'security', fn ($query) => $query->where('security_related', true))
            ->when($classification === 'platform', fn ($query) => $query->whereIn('http_status', [500, 503]))
            ->when($classification === 'operational', fn ($query) => $query->where('security_related', false)->whereNotIn('http_status', [500, 503]))
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $metrics = [
            'new' => ErrorOccurrence::where('status', 'new')->count(),
            'investigating' => ErrorOccurrence::where('status', 'investigating')->count(),
            'security' => ErrorOccurrence::where('security_related', true)->count(),
            'platform' => ErrorOccurrence::whereIn('http_status', [500, 503])->whereIn('status', ['new', 'investigating'])->count(),
        ];
        $tenants = Tenant::query()->orderBy('name')->get(['id', 'name']);

        $this->recordAccess($request, 'platform.occurrences.index');

        return view('platform.occurrences.index', compact(
            'occurrences', 'metrics', 'tenants', 'search', 'status', 'classification', 'tenantId'
        ));
    }

    public function show(Request $request, ErrorOccurrence $occurrence): View
    {
        $occurrence->load(['tenant:id,name,slug', 'actor:id,name,email', 'reviewer:id,name,email']);
        $this->recordAccess($request, 'platform.occurrences.show', $occurrence);

        return view('platform.occurrences.show', compact('occurrence'));
    }

    public function update(Request $request, ErrorOccurrence $occurrence): RedirectResponse
    {
        $request->merge(['security_related' => $request->boolean('security_related')]);
        $request->merge(['personal_data_incident' => $request->boolean('personal_data_incident')]);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ErrorOccurrence::STATUS_LABELS))],
            'security_related' => ['required', 'boolean'],
            'personal_data_incident' => ['required', 'boolean'],
            'risk_assessment' => [Rule::requiredIf($request->boolean('personal_data_incident')), 'nullable', Rule::in(['pending', 'no_relevant_risk', 'relevant_risk'])],
            'affected_subjects_estimate' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'affected_data_categories' => [Rule::requiredIf($request->boolean('personal_data_incident')), 'nullable', 'string', 'max:4000'],
            'containment_measures' => [Rule::requiredIf($request->boolean('personal_data_incident')), 'nullable', 'string', 'max:4000'],
            'anpd_notified_at' => ['nullable', 'date'],
            'data_subjects_notified_at' => ['nullable', 'date'],
            'internal_notes' => [
                Rule::requiredIf($request->boolean('security_related')),
                'nullable',
                'string',
                'max:4000',
            ],
        ], [
            'internal_notes.required' => 'Registre um resumo da investigação ao classificar a ocorrência como segurança.',
            'risk_assessment.required' => 'Informe a avaliação de risco do incidente com dados pessoais.',
            'affected_data_categories.required' => 'Informe as categorias de dados pessoais potencialmente afetadas.',
            'containment_measures.required' => 'Registre as medidas de contenção adotadas.',
        ]);

        $personalDataIncident = (bool) $data['personal_data_incident'];
        $securityRelated = (bool) $data['security_related'] || $personalDataIncident;

        if ($occurrence->personal_data_incident && ! $personalDataIncident) {
            return back()->withErrors([
                'personal_data_incident' => 'Um incidente confirmado com dados pessoais não pode ser rebaixado. Corrija os dados da investigação ou consulte o responsável jurídico.',
            ])->withInput();
        }

        $incidentConfirmedAt = $personalDataIncident
            ? ($occurrence->incident_confirmed_at ?? now())
            : null;
        $retentionUntil = match (true) {
            $personalDataIncident => $incidentConfirmedAt->copy()->addYears((int) config('security.personal_data_incident_retention_years', 5)),
            $securityRelated => now()->addDays(max(30, (int) config('security.security_error_retention_days', 180))),
            default => $occurrence->created_at->copy()->addDays(max(30, (int) config('security.error_retention_days', 90))),
        };

        $occurrence->update([
            'status' => $data['status'],
            'security_related' => $securityRelated,
            'personal_data_incident' => $personalDataIncident,
            'risk_assessment' => $personalDataIncident ? $data['risk_assessment'] : null,
            'affected_subjects_estimate' => $personalDataIncident ? ($data['affected_subjects_estimate'] ?? null) : null,
            'affected_data_categories' => $personalDataIncident ? $data['affected_data_categories'] : null,
            'containment_measures' => $personalDataIncident ? $data['containment_measures'] : null,
            'incident_confirmed_at' => $incidentConfirmedAt,
            'anpd_notified_at' => $personalDataIncident ? ($data['anpd_notified_at'] ?? null) : null,
            'data_subjects_notified_at' => $personalDataIncident ? ($data['data_subjects_notified_at'] ?? null) : null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'reviewed_by_user_id' => $request->user()->id,
            'resolved_at' => $data['status'] === 'resolved' ? ($occurrence->resolved_at ?? now()) : null,
            'retention_until' => $retentionUntil,
        ]);

        $retentionMessage = $personalDataIncident
            ? config('security.personal_data_incident_retention_years', 5).' anos'
            : ($securityRelated ? config('security.security_error_retention_days', 180) : config('security.error_retention_days', 90)).' dias';

        return back()->with('success', 'Ocorrência atualizada. Retenção definida para '.$retentionMessage.'.');
    }

    private function recordAccess(Request $request, string $event, ?ErrorOccurrence $occurrence = null): void
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
                'metadata' => [
                    'outcome' => 'success',
                    'occurrence_protocol' => $occurrence?->protocol,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Não foi possível auditar a consulta de ocorrências.', [
                'event' => $event,
                'exception' => $exception::class,
            ]);
        }
    }
}
