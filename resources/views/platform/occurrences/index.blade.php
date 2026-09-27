@extends('layouts.platform', ['title' => 'Central de ocorrências', 'eyebrow' => 'Investigação e suporte'])

@section('content')
    <section class="occurrence-metrics" aria-label="Resumo das ocorrências">
        <article><span>Novas</span><strong>{{ $metrics['new'] }}</strong><small>Aguardando triagem</small></article>
        <article><span>Em investigação</span><strong>{{ $metrics['investigating'] }}</strong><small>Análise em andamento</small></article>
        <article><span>Segurança</span><strong>{{ $metrics['security'] }}</strong><small>Triagem por {{ config('security.security_error_retention_days', 180) }} dias</small></article>
        <article><span>Falhas da plataforma</span><strong>{{ $metrics['platform'] }}</strong><small>HTTP 500 e 503 ativos</small></article>
    </section>

    <section class="panel-card occurrence-panel">
        <div class="panel-heading occurrence-heading">
            <div>
                <p class="eyebrow">Protocolos registrados</p>
                <h2>Ocorrências da plataforma</h2>
                <p>Falhas 500 e 503 entram automaticamente em investigação. Nenhuma senha, código MFA ou conteúdo de formulário é armazenado.</p>
            </div>
            <form method="get" class="occurrence-filters">
                <input name="q" value="{{ $search }}" placeholder="Protocolo, código, rota ou e-mail" aria-label="Pesquisar ocorrências">
                <select name="status" aria-label="Filtrar por situação">
                    <option value="">Todas as situações</option>
                    @foreach(\App\Models\ErrorOccurrence::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="classification" aria-label="Filtrar por classificação">
                    <option value="">Todas as classificações</option>
                    <option value="operational" @selected($classification === 'operational')>Operacionais</option>
                    <option value="platform" @selected($classification === 'platform')>Falhas da plataforma</option>
                    <option value="security" @selected($classification === 'security')>Segurança reportada</option>
                </select>
                <select name="tenant_id" aria-label="Filtrar por empresa">
                    <option value="">Todas as empresas</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected($tenantId === $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
                <button class="secondary-button" type="submit">Filtrar</button>
            </form>
        </div>

        <div class="occurrence-table" role="table" aria-label="Ocorrências registradas">
            <div class="occurrence-row occurrence-head" role="row">
                <span>Protocolo</span><span>Erro</span><span>Empresa e usuário</span><span>Rota</span><span>Situação</span><span>Retenção</span><span></span>
            </div>
            @forelse($occurrences as $occurrence)
                <div class="occurrence-row" role="row">
                    <span><strong><code>{{ $occurrence->protocol }}</code></strong><small>{{ \App\Support\LocalDateTime::format($occurrence->created_at, 'd/m/Y H:i:s') }}</small></span>
                    <span><strong>{{ $occurrence->error_code }}</strong><small>HTTP {{ $occurrence->http_status }}{{ $occurrence->personal_data_incident ? ' · Incidente LGPD' : ($occurrence->security_related ? ' · Segurança' : '') }}</small></span>
                    <span><strong>{{ $occurrence->tenant?->name ?? 'Plataforma/público' }}</strong><small>{{ $occurrence->actor?->email ?? 'Não autenticado' }}</small></span>
                    <span><strong>{{ $occurrence->method }}</strong><small>{{ $occurrence->path }}</small></span>
                    <span><span class="occurrence-status {{ $occurrence->status }}">{{ $occurrence->statusLabel() }}</span></span>
                    <span><strong>{{ \App\Support\LocalDateTime::format($occurrence->retention_until, 'd/m/Y') }}</strong><small>{{ $occurrence->personal_data_incident ? config('security.personal_data_incident_retention_years', 5).' anos' : ($occurrence->security_related ? config('security.security_error_retention_days', 180).' dias' : config('security.error_retention_days', 90).' dias') }}</small></span>
                    <span><a class="table-link" href="{{ route('platform.occurrences.show', $occurrence) }}">Analisar</a></span>
                </div>
            @empty
                <div class="empty-state compact"><h3>Nenhuma ocorrência encontrada</h3><p>Altere os filtros ou aguarde um novo registro.</p></div>
            @endforelse
        </div>
    </section>

    <div class="pagination">{{ $occurrences->links() }}</div>
@endsection
