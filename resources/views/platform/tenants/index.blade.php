@extends('layouts.platform', ['title' => 'Empresas', 'eyebrow' => 'Clientes da plataforma'])

@section('content')
    <div class="action-bar platform-actions">
        <form method="get" class="tenant-filters">
            <label class="sr-only" for="tenant-search">Buscar empresa</label>
            <input id="tenant-search" name="q" value="{{ $search }}" placeholder="Nome, identificador ou domínio">
            <select name="status" aria-label="Filtrar por situação">
                <option value="">Todas as situações</option>
                <option value="active" @selected($status === 'active')>Ativas</option>
                <option value="suspended" @selected($status === 'suspended')>Suspensas</option>
            </select>
            <button class="secondary-button" type="submit">Filtrar</button>
        </form>
        <a class="primary-button" href="{{ route('platform.tenants.create') }}">+ Nova empresa</a>
    </div>

    <section class="panel-card tenant-table-card">
        <div class="tenant-table-head"><span>Empresa</span><span>Plano</span><span>Conteúdo</span><span>Situação</span><span></span></div>
        @forelse($tenants as $tenant)
            <div class="tenant-table-row">
                <div class="tenant-identity"><span class="item-thumb">{{ mb_substr($tenant->name, 0, 1) }}</span><div><strong>{{ $tenant->name }}</strong><small>{{ $tenant->custom_domain ?: $tenant->slug }}<br>{{ $tenant->owner?->email ?? 'Sem administrador' }}</small></div></div>
                <span class="plan-badge">{{ ucfirst($tenant->plan) }}</span>
                <span class="tenant-count">{{ $tenant->products_count }} produtos · {{ $tenant->users_count }} usuários</span>
                <span class="status {{ $tenant->status }}">{{ $tenant->status === 'active' ? 'Ativa' : 'Suspensa' }}</span>
                <div class="row-actions"><a href="{{ route('platform.tenants.edit', $tenant) }}">Gerenciar</a></div>
            </div>
        @empty
            <div class="empty-state compact"><h3>Nenhuma empresa encontrada</h3><p>Altere os filtros ou cadastre o primeiro cliente.</p></div>
        @endforelse
    </section>

    <div class="pagination">{{ $tenants->links() }}</div>
@endsection
