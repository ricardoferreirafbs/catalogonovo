@extends('layouts.catalog')

@section('content')
    <section class="catalog-hero container">
        <div class="hero-copy">
            <p class="eyebrow">Catálogo {{ date('Y') }}</p>
            <h1>{{ $tenant->themeValue('hero_title', 'Produtos escolhidos para você.') }}</h1>
            <p>{{ $tenant->themeValue('hero_text', 'Explore a coleção e fale com nossa equipe para saber mais.') }}</p>
            <a href="#catalogo" class="primary-button">Explorar coleção <span>↓</span></a>
        </div>
        <div class="hero-composition" aria-hidden="true">
            <span class="hero-orbit"></span>
            <div class="hero-card hero-card-main"><span>Seleção</span><strong>{{ $products->count() }}</strong><small>produtos disponíveis</small></div>
            <div class="hero-card hero-card-accent">Feito<br>para<br>descobrir.</div>
        </div>
    </section>

    <div id="catalogo" class="container">
        <div
            id="catalog-app"
            data-products='@json($products)'
            data-categories='@json($categories->map->only(["id", "name"]))'
            data-currency="BRL"
        ></div>
    </div>
@endsection
