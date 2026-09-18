@extends('layouts.platform', ['title' => 'Auditoria', 'eyebrow' => 'Rastreabilidade da plataforma'])

@section('content')
    <section class="panel-card audit-panel">
        <div class="panel-heading audit-heading">
            <div><p class="eyebrow">Eventos de segurança</p><h2>Operações registradas</h2><p>O conteúdo de senhas e formulários não é armazenado nesta trilha.</p></div>
            <form method="get" class="audit-filters">
                <input name="event" value="{{ $event }}" placeholder="Filtrar por evento ou rota">
                <select name="outcome">
                    <option value="">Todos os resultados</option>
                    <option value="success" @selected($outcome === 'success')>Sucesso</option>
                    <option value="rejected" @selected($outcome === 'rejected')>Rejeitado</option>
                </select>
                <button class="secondary-button" type="submit">Filtrar</button>
            </form>
        </div>

        <div class="audit-table" role="table" aria-label="Registros de auditoria">
            <div class="audit-row audit-head" role="row">
                <span>Data</span><span>Evento</span><span>Responsável</span><span>Empresa</span><span>Origem</span><span>Resultado</span>
            </div>
            @forelse($logs as $log)
                <div class="audit-row" role="row">
                    <span><strong>{{ $log->created_at->format('d/m/Y') }}</strong><small>{{ $log->created_at->format('H:i:s') }}</small></span>
                    <span><strong>{{ $log->event }}</strong><small>{{ $log->method }} /{{ $log->path }}</small></span>
                    <span><strong>{{ $log->actor?->name ?? 'Não autenticado' }}</strong><small>{{ $log->actor?->email ?? '—' }}</small></span>
                    <span>{{ $log->tenant?->name ?? ($log->tenant_id ? '#'.$log->tenant_id : 'Plataforma') }}</span>
                    <span><small>{{ $log->ip_address ?: '—' }}</small></span>
                    <span><span class="audit-outcome {{ data_get($log->metadata, 'outcome') }}">{{ data_get($log->metadata, 'outcome') === 'success' ? 'Sucesso' : 'Rejeitado' }}</span><small>HTTP {{ $log->status }}</small></span>
                </div>
            @empty
                <div class="empty-inline">Nenhum evento encontrado.</div>
            @endforelse
        </div>

        <div class="pagination">{{ $logs->links() }}</div>
    </section>
@endsection
