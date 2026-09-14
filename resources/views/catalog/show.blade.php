@extends('layouts.catalog', ['title' => $product->name.' — '.$tenant->name, 'metaDescription' => Str::limit($product->description, 150)])

@section('content')
    <article class="product-detail container">
        <a href="{{ route('catalog.index') }}" class="back-link">← Voltar ao catálogo</a>
        <div class="product-detail-grid">
            <div class="product-detail-media">
                <img src="{{ $product->media->first() ? Storage::disk('public')->url($product->media->first()->path) : asset('images/product-placeholder.svg') }}" alt="{{ $product->name }}">
            </div>
            <div class="product-detail-copy">
                <p class="eyebrow">{{ $product->category?->name ?? 'Produto' }} @if($product->sku) · {{ $product->sku }} @endif</p>
                <h1>{{ $product->name }}</h1>
                @if($product->stock_label)<span class="stock-pill">{{ $product->stock_label }}</span>@endif
                <p class="detail-description">{{ $product->description }}</p>
                <div class="detail-price">
                    @if($product->price)
                        @if($product->promotional_price)<del>R$ {{ number_format($product->price, 2, ',', '.') }}</del>@endif
                        <strong>R$ {{ number_format($product->current_price, 2, ',', '.') }}</strong>
                    @else
                        <strong>Preço sob consulta</strong>
                    @endif
                </div>
                @if($tenant->contact_phone)
                    @php($message = $product->whatsapp_message ?: "Olá! Gostaria de saber mais sobre {$product->name}.")
                    <a class="primary-button full-button" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', $tenant->contact_phone) }}?text={{ urlencode($message) }}">Consultar pelo WhatsApp ↗</a>
                @endif
            </div>
        </div>
    </article>
@endsection
