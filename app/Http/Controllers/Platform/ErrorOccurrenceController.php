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
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ErrorOccurrence::STATUS_LABELS))],
            'security_related' => ['required', 'boolean'],
            'internal_notes' => [
                Rule::requiredIf($request->boolean('security_related')),
                'nullable',
                'string',
                'max:4000',
            ],
        ], [
            'internal_notes.required' => 'Registre um resumo da investigação ao classificar a ocorrência como segurança.',
        ]);

        $securityRelated = (bool) $data['security_related'];
        $retentionDays = max(30, (int) ($securityRelated
            ? config('security.security_error_retention_days', 180)
            : config('security.error_retention_days', 90)));

        $occurrence->update([
            'status' => $data['status'],
            'security_related' => $securityRelated,
            'internal_notes' => $data['internal_notes'] ?? null,
            'reviewed_by_user_id' => $request->user()->id,
            'resolved_at' => $data['status'] === 'resolved' ? ($occurrence->resolved_at ?? now()) : null,
            'retention_until' => $securityRelated
                ? now()->addDays($retentionDays)
                : $occurrence->created_at->copy()->addDays($retentionDays),
        ]);

        return back()->with('success', 'Ocorrência atualizada. Retenção definida para '.$retentionDays.' dias.');
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
