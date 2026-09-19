@extends('layouts.admin', ['title' => 'Visão geral', 'eyebrow' => 'Olá, '.explode(' ', auth()->user()->name)[0]])

@section('content')
    <section class="metric-grid" aria-label="Resumo do catálogo">
        <article class="metric-card"><span>Total de produtos</span><strong>{{ $metrics['products'] }}</strong><small>Itens cadastrados</small></article>
        <article class="metric-card featured"><span>Publicados</span><strong>{{ $metrics['published'] }}</strong><small>Visíveis no catálogo</small></article>
        <article class="metric-card"><span>Categorias</span><strong>{{ $metrics['categories'] }}</strong><small>Grupos ativos</small></article>
        <article class="metric-card"><span>Destaques</span><strong>{{ $metrics['featured'] }}</strong><small>Na vitrine principal</small></article>
    </section>

    <section class="admin-grid">
        <div class="panel-card wide-panel">
            <div class="panel-heading"><div><p class="eyebrow">Conteúdo</p><h2>Produtos recentes</h2></div>@if(auth()->user()->hasPermission('products.manage'))<a href="{{ route('admin.products.create') }}" class="secondary-button">+ Novo produto</a>@endif</div>
            <div class="data-list">
                @forelse($recentProducts as $product)
                    <div class="data-row">
                        <span class="item-thumb">{{ mb_substr($product->name, 0, 1) }}</span>
                        <span class="item-main"><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Sem categoria' }} · {{ $product->sku ?: 'Sem código' }}</small></span>
                        <span class="status {{ $product->status }}">{{ $product->status === 'published' ? 'Publicado' : 'Rascunho' }}</span>
                        <span class="row-arrow">›</span>
                    </div>
                @empty
                    <div class="empty-inline">Ainda não há produtos. Comece pelo primeiro cadastro.</div>
                @endforelse
            </div>
        </div>

        @if(auth()->user()->hasPermission('structure.manage'))<div class="panel-card quick-panel">
            <p class="eyebrow">Atalho</p><h2>Nova categoria</h2><p>Organize os produtos para facilitar a navegação.</p>
            <form action="{{ route('admin.categories.store') }}" method="post" class="stack-form">
                @csrf
                <label>Nome<input name="name" required maxlength="100" placeholder="Ex.: Lançamentos"></label>
                <label>Identificador opcional<input name="slug" maxlength="120" placeholder="lancamentos"></label>
                <button class="primary-button" type="submit">Adicionar categoria</button>
            </form>
            <div class="category-list">
                @foreach($tenant->categories()->orderBy('sort_order')->get() as $category)
                    <span>{{ $category->name }} <small>{{ $category->products()->count() }}</small></span>
                @endforeach
            </div>
        </div>@endif
    </section>
@endsection
