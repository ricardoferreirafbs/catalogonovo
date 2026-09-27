@extends('layouts.platform', ['title' => 'Solicitações de privacidade', 'eyebrow' => 'Direitos dos titulares'])

@section('content')
    <section class="occurrence-metrics" aria-label="Resumo das solicitações">
        <article><span>Confirmadas</span><strong>{{ $metrics['verified'] }}</strong><small>Aguardando triagem</small></article>
        <article><span>Em análise</span><strong>{{ $metrics['in_review'] }}</strong><small>Tratativa em andamento</small></article>
        <article><span>Concluídas</span><strong>{{ $metrics['completed'] }}</strong><small>Pedidos atendidos</small></article>
        <article><span>Sem confirmação</span><strong>{{ $metrics['awaiting'] }}</strong><small>Expiram automaticamente</small></article>
    </section>

    <section class="panel-card privacy-admin-panel">
        <div class="panel-heading occurrence-heading">
            <div><p class="eyebrow">Canal do titular</p><h2>Pedidos recebidos</h2><p>Os campos pessoais são criptografados. Confirme a identidade e a responsabilidade da Catalog antes de entregar, corrigir ou excluir qualquer dado.</p></div>
            <form method="get" class="occurrence-filters">
                <input name="q" value="{{ $search }}" placeholder="Protocolo" aria-label="Pesquisar protocolo">
                <select name="status"><option value="">Todas as situações</option>@foreach(\App\Models\PrivacyRequest::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
                <select name="type"><option value="">Todos os direitos</option>@foreach(\App\Models\PrivacyRequest::TYPE_LABELS as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>@endforeach</select>
                <button class="secondary-button" type="submit">Filtrar</button>
            </form>
        </div>
        <div class="privacy-admin-list">
            @forelse($requests as $privacyRequest)
                <article>
                    <div><code>{{ $privacyRequest->protocol }}</code><strong>{{ $privacyRequest->typeLabel() }}</strong><small>{{ \App\Support\LocalDateTime::format($privacyRequest->created_at) }} · {{ $privacyRequest->scope === 'tenant' ? 'Empresa cliente' : 'Plataforma' }}</small></div>
                    <span class="occurrence-status {{ $privacyRequest->status }}">{{ $privacyRequest->statusLabel() }}</span>
                    <a class="table-link" href="{{ route('platform.privacy.show', $privacyRequest) }}">Analisar</a>
                </article>
            @empty
                <div class="empty-state compact"><h3>Nenhuma solicitação encontrada</h3><p>Altere os filtros ou aguarde um novo pedido.</p></div>
            @endforelse
        </div>
    </section>
    <div class="pagination">{{ $requests->links() }}</div>
@endsection
