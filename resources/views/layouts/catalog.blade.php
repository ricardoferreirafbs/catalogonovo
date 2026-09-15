<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? $tenant->themeValue('hero_text', 'Conheça nosso catálogo de produtos.') }}">
    <title>{{ $title ?? $tenant->name }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --brand: {{ $tenant->themeValue('primary', '#173f35') }};
            --accent: {{ $tenant->themeValue('accent', '#e48a4a') }};
            --surface: {{ $tenant->themeValue('surface', '#f4f6f3') }};
            --card-radius: {{ $tenant->themeValue('card_style', 'soft') === 'square' ? '2px' : ($tenant->themeValue('card_style', 'soft') === 'outline' ? '10px' : '24px') }};
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="catalog-body font-{{ $tenant->themeValue('font_style', 'modern') }}">
    <header class="catalog-header container">
        <a href="{{ route('catalog.index') }}" class="brand" aria-label="Página inicial de {{ $tenant->name }}">
            @if($tenant->logo_path)
                <img src="{{ Storage::disk('uploads')->url($tenant->logo_path) }}" alt="{{ $tenant->name }}">
            @else
                <span class="brand-mark">{{ mb_substr($tenant->name, 0, 1) }}</span>
                <span>{{ $tenant->name }}</span>
            @endif
        </a>
        <nav aria-label="Navegação principal">
            <a href="{{ route('catalog.index') }}#catalogo">Produtos</a>
            @if($tenant->contact_phone)
                <a class="header-cta" href="https://wa.me/{{ preg_replace('/\D/', '', $tenant->contact_phone) }}" target="_blank" rel="noopener">Fale conosco</a>
            @endif
        </nav>
    </header>
    <main>@yield('content')</main>
    <footer class="catalog-footer">
        <div class="container"><strong>{{ $tenant->name }}</strong><span>Catálogo atualizado e seguro.</span></div>
    </footer>
</body>
</html>
