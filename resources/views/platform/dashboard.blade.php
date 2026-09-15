@extends('layouts.platform', ['title' => 'Visão geral', 'eyebrow' => 'Operação SaaS'])

@section('content')
    <section class="metric-grid" aria-label="Resumo da plataforma">
        <article class="metric-card featured"><span>Empresas ativas</span><strong>{{ $metrics['active'] }}</strong><small>de {{ $metrics['tenants'] }} cadastradas</small></article>
        <article class="metric-card"><span>Empresas totais</span><strong>{{ $metrics['tenants'] }}</strong><small>Contas na plataforma</small></article>
        <article class="metric-card"><span>Produtos</span><strong>{{ $metrics['products'] }}</strong><small>Itens em todos os catálogos</small></article>
        <article class="metric-card"><span>Usuários de clientes</span><strong>{{ $metrics['users'] }}</strong><small>Acessos vinculados</small></article>
    </section>

    <section class="panel-card wide-panel platform-recent">
        <div class="panel-heading">
            <div><p class="eyebrow">Clientes recentes</p><h2>Empresas cadastradas</h2></div>
            <a href="{{ route('platform.tenants.create') }}" class="primary-button">+ Nova empresa</a>
        </div>
        <div class="data-list">
            @forelse($recentTenants as $tenant)
                <a href="{{ route('platform.tenants.edit', $tenant) }}" class="data-row platform-data-row">
                    <span class="item-thumb">{{ mb_substr($tenant->name, 0, 1) }}</span>
                    <span class="item-main"><strong>{{ $tenant->name }}</strong><small>{{ $tenant->custom_domain ?: $tenant->slug }} · {{ $tenant->owner?->email ?? 'Sem administrador' }}</small></span>
                    <span class="tenant-count">{{ $tenant->products_count }} produtos</span>
                    <span class="status {{ $tenant->status }}">{{ $tenant->status === 'active' ? 'Ativa' : 'Suspensa' }}</span>
                    <span class="row-arrow">›</span>
                </a>
            @empty
                <div class="empty-inline">Nenhuma empresa cadastrada.</div>
            @endforelse
        </div>
    </section>
@endsection
