@php
    $content = $content ?? array_replace_recursive(\App\Http\Controllers\Admin\ContentController::defaults(), $tenant->content ?? []);
    $menuCategories = $menuCategories ?? $tenant->categories()->where('is_active', true)->where('show_in_menu', true)->orderBy('sort_order')->get();
    $menuItems = $menuItems ?? $tenant->menuItems()->where('is_active', true)->orderBy('sort_order')->get();
    $template = $tenant->themeValue('template', 'classic');
    $isMimo = $template === 'mimo';
    $phone = preg_replace('/\D/', '', $tenant->contact_phone ?? '');
    $whatsapp = $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode('Olá! Vim pelo catálogo de '.$tenant->name.' e gostaria de saber mais.') : null;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? ($content['seo_description'] ?: $content['hero_text']) }}">
    <title>{{ $title ?? ($content['seo_title'] ?: $tenant->name) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>:root{--brand:{{ $tenant->themeValue('primary','#173f35') }};--accent:{{ $tenant->themeValue('accent','#e48a4a') }};--surface:{{ $tenant->themeValue('surface','#f4f6f3') }};--ink:{{ $tenant->themeValue('dark','#111a35') }};--card-radius:{{ $tenant->themeValue('card_style','soft')==='square'?'2px':($tenant->themeValue('card_style','soft')==='outline'?'10px':'24px') }};}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="catalog-body template-{{ $template }} font-{{ $tenant->themeValue('font_style','modern') }}">
    <header class="catalog-header {{ $template === 'accesso' ? 'accesso-header' : ($isMimo ? 'mimo-header' : '') }} container">
        <a href="{{ route('catalog.index') }}" class="brand" aria-label="Página inicial de {{ $tenant->name }}">
            @if($tenant->logo_path)<img src="{{ Storage::disk('uploads')->url($tenant->logo_path) }}" alt="{{ $tenant->name }}">@else<span class="brand-mark">{{ $isMimo ? '♡' : mb_substr($tenant->name,0,2) }}</span><span>{{ $tenant->name }}</span>@endif
        </a>
        <button class="mobile-menu-button" type="button" aria-label="Abrir menu" aria-expanded="false" onclick="this.setAttribute('aria-expanded',this.getAttribute('aria-expanded')!=='true');this.nextElementSibling.classList.toggle('open')">☰</button>
        <nav aria-label="Navegação principal">
            @foreach($menuItems as $item)<a href="{{ $item->url }}" @if($item->open_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>@endforeach
            @foreach($menuCategories as $category)<a href="{{ route('catalog.index', ['categoria' => $category->slug]) }}#catalogo">{{ $category->name }}</a>@endforeach
            @if($menuItems->isEmpty() && $menuCategories->isEmpty())<a href="{{ route('catalog.index') }}#catalogo">Catálogo</a>@if($template==='accesso')<a href="{{ route('catalog.index') }}#experiencia">Experiência</a><a href="{{ route('catalog.index') }}#como-pedir">Como pedir</a>@elseif($isMimo)<a href="{{ route('catalog.index') }}#como-pedir">Como funciona</a><a href="{{ route('catalog.index') }}#sobre">Sobre</a>@endif @endif
            @if($whatsapp)<a class="header-cta" href="{{ $whatsapp }}" target="_blank" rel="noopener">{{ $isMimo ? 'Pedir orçamento' : 'Falar no WhatsApp' }} <span>↗</span></a>@endif
        </nav>
    </header>
    <main>@yield('content')</main>
    <footer class="catalog-footer {{ $template === 'accesso' ? 'accesso-footer' : ($isMimo ? 'mimo-footer' : '') }}">
        @if($isMimo)
            <div class="container mimo-footer-inner"><div><a href="#top" class="mimo-footer-brand">♡ {{ $tenant->name }}</a><p>{{ $content['footer_text'] }}</p></div>@if($content['footer_social_url'])<a href="{{ $content['footer_social_url'] }}" target="_blank" rel="noopener">{{ $content['footer_social_label'] }} ↗</a>@endif<small>© {{ date('Y') }} {{ $tenant->name }}</small></div>
        @else
            <div class="container footer-grid"><div><strong>{{ $tenant->name }}</strong><p>{{ $content['footer_text'] }}</p></div><div><b>Navegação</b><a href="{{ route('catalog.index') }}#catalogo">Catálogo</a>@if($content['show_steps'])<a href="{{ route('catalog.index') }}#como-pedir">Como pedir</a>@endif</div>@if($menuCategories->isNotEmpty())<div><b>Categorias</b>@foreach($menuCategories->take(4) as $category)<a href="{{ route('catalog.index',['categoria'=>$category->slug]) }}#catalogo">{{ $category->name }}</a>@endforeach</div>@endif</div>
        @endif
    </footer>
    @if($whatsapp)<a class="floating-whatsapp {{ $isMimo ? 'mimo-floating' : '' }}" href="{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="Fale conosco no WhatsApp"><span>{{ $isMimo ? '♡' : 'WA' }}</span> {{ $isMimo ? 'Pedir orçamento' : 'Fale conosco' }}</a>@endif
</body>
</html>
