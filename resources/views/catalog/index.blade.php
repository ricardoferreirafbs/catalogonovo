@extends('layouts.catalog')

@section('content')
@php
    $isAccesso = $tenant->themeValue('template', 'classic') === 'accesso';
    $heroProduct = $products->firstWhere('featured', true) ?? $products->first();
    $heroImage = $tenant->hero_image_path ? Storage::disk('uploads')->url($tenant->hero_image_path) : data_get($heroProduct, 'image');
    $phone = preg_replace('/\D/', '', $tenant->contact_phone ?? '');
    $whatsapp = $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode('Olá! Vim pelo catálogo de '.$tenant->name.' e gostaria de saber mais.') : null;
    $categoryPayload = $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'parent_id' => $category->parent_id, 'depth' => $category->depth()]);
@endphp

@if($isAccesso)
    <section id="top" class="accesso-hero container">
        <div class="accesso-hero-copy">
            <p class="accesso-eyebrow">{{ $content['hero_eyebrow'] }}</p>
            <h1>{{ $content['hero_title'] }} <em>{{ $content['hero_highlight'] }}</em></h1>
            <p class="accesso-lead">{{ $content['hero_text'] }}</p>
            <div class="hero-actions"><a class="accesso-button dark" href="{{ $content['hero_primary_url'] ?: '#catalogo' }}">{{ $content['hero_primary_label'] }} ↗</a>@if($content['hero_secondary_label'])<a class="accesso-button light" href="{{ $content['hero_secondary_url'] ?: '#como-pedir' }}">{{ $content['hero_secondary_label'] }}</a>@endif @if($whatsapp)<a class="accesso-button whatsapp" href="{{ $whatsapp }}" target="_blank" rel="noopener">Falar com a {{ $tenant->name }}</a>@endif</div>
            @if($content['show_stats'])<div class="hero-stats">@for($i=1;$i<=3;$i++)<div><strong>{{ $content["stat_{$i}_value"] }}</strong><span>{{ $content["stat_{$i}_label"] }}</span></div>@endfor</div>@endif
        </div>
        <div class="accesso-hero-visual">
            <span class="visual-orbit"></span><span class="visual-disc"></span>
            @if($heroImage)<img src="{{ $heroImage }}" alt="Destaque de {{ $tenant->name }}">@else<div class="hero-placeholder">{{ mb_substr($tenant->name,0,1) }}</div>@endif
            <span class="visual-note">● {{ $products->count() }} produtos disponíveis</span>
        </div>
    </section>
    @if($content['show_marquee'])<div class="accesso-marquee" aria-label="Destaques"><div>{{ $content['marquee'] }} &nbsp; ✦ &nbsp; {{ $content['marquee'] }}</div></div>@endif
@else
    <section class="catalog-hero container"><div class="hero-copy"><p class="eyebrow">{{ $content['hero_eyebrow'] }}</p><h1>{{ $content['hero_title'] }} {{ $content['hero_highlight'] }}</h1><p>{{ $content['hero_text'] }}</p><a href="#catalogo" class="primary-button">{{ $content['hero_primary_label'] }} <span>↓</span></a></div><div class="hero-composition" aria-hidden="true"><span class="hero-orbit"></span><div class="hero-card hero-card-main"><span>Seleção</span><strong>{{ $products->count() }}</strong><small>produtos disponíveis</small></div><div class="hero-card hero-card-accent">Feito<br>para<br>descobrir.</div></div></section>
@endif

<div id="catalogo" class="container {{ $isAccesso ? 'accesso-catalog-wrap' : '' }}">
    <div id="catalog-app" data-products='@json($products)' data-categories='@json($categoryPayload)' data-currency="BRL" data-initial-category="{{ request('categoria') }}" data-eyebrow="{{ $content['catalog_eyebrow'] }}" data-title="{{ $content['catalog_title'] }}" data-description="{{ $content['catalog_text'] }}"></div>
</div>

@if($isAccesso && $content['show_experience'])
<section id="experiencia" class="accesso-experience"><div class="container experience-grid"><div class="experience-art"><span>{{ mb_strtoupper(mb_substr($tenant->name,0,3)) }}</span><strong>{{ $products->count() }}</strong><small>itens na coleção</small></div><div><p class="accesso-eyebrow">{{ $content['experience_eyebrow'] }}</p><h2>{{ $content['experience_title'] }}</h2><p class="section-lead">{{ $content['experience_text'] }}</p><ol class="experience-list">@for($i=1;$i<=3;$i++)<li><span>0{{ $i }}</span><div><strong>{{ $content["experience_item_{$i}_title"] }}</strong><p>{{ $content["experience_item_{$i}_text"] }}</p></div></li>@endfor</ol></div></div></section>
@endif

@if($isAccesso && $content['show_steps'])
<section id="como-pedir" class="accesso-steps container"><p class="accesso-eyebrow">{{ $content['steps_eyebrow'] }}</p><h2>{{ $content['steps_title'] }}</h2><div class="steps-grid">@for($i=1;$i<=3;$i++)<article><span>0{{ $i }}</span><h3>{{ $content["step_{$i}_title"] }}</h3><p>{{ $content["step_{$i}_text"] }}</p></article>@endfor</div></section>
@endif

@if($isAccesso && $content['show_contact'] && $whatsapp)
<section class="accesso-contact container"><div><p class="accesso-eyebrow">{{ $content['contact_eyebrow'] }}</p><h2>{{ $content['contact_title'] }}</h2><p>{{ $content['contact_text'] }}</p></div><a href="{{ $whatsapp }}" target="_blank" rel="noopener">{{ $content['contact_button'] }} ↗</a></section>
@endif

@if($isAccesso && $content['show_faq'])
<section class="accesso-faq container"><div><p class="accesso-eyebrow">{{ $content['faq_eyebrow'] }}</p><h2>{{ $content['faq_title'] }}</h2></div><div>@for($i=1;$i<=3;$i++)@if($content["faq_{$i}_question"])<details><summary>{{ $content["faq_{$i}_question"] }} <span>+</span></summary><p>{{ $content["faq_{$i}_answer"] }}</p></details>@endif @endfor</div></section>
@endif
@endsection
