@extends('layouts.admin', ['title' => 'Produtos', 'eyebrow' => 'Catálogo'])

@section('content')
    <div class="action-bar">
        <p>{{ $products->total() }} itens cadastrados. Valores e fotos seguem o mesmo padrão para toda a plataforma.</p>
        <a href="{{ route('admin.products.create') }}" class="primary-button">+ Novo produto</a>
    </div>
    <section class="panel-card product-table-card">
        <div class="product-admin-list">
            @forelse($products as $product)
                <article class="product-admin-row">
                    <img src="{{ $product->media->first() ? Storage::disk('uploads')->url($product->media->first()->path) : asset('images/product-placeholder.svg') }}" alt="">
                    <div class="item-main"><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Sem categoria' }} · {{ $product->sku ?: 'Sem código' }}</small></div>
                    <div class="admin-price">{{ $product->current_price ? 'R$ '.number_format($product->current_price, 2, ',', '.') : 'Sob consulta' }}</div>
                    <span class="status {{ $product->status }}">{{ $product->status === 'published' ? 'Publicado' : 'Rascunho' }}</span>
                    <div class="row-actions">
                        <a href="{{ route('admin.products.edit', $product) }}">Editar</a>
                        <form method="post" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Excluir este produto?')">@csrf @method('DELETE')<button type="submit">Excluir</button></form>
                    </div>
                </article>
            @empty
                <div class="empty-state compact"><h3>Seu catálogo está vazio</h3><p>Cadastre o primeiro produto para começar.</p></div>
            @endforelse
        </div>
        @if($products->hasPages())<div class="pagination">{{ $products->links() }}</div>@endif
    </section>
@endsection
