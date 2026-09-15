@extends('layouts.catalog')

@section('content')
@php
    $isAccesso = $tenant->themeValue('template', 'classic') === 'accesso';
    $isMimo = $tenant->themeValue('template', 'classic') === 'mimo';
    $heroProduct = $products->firstWhere('featured', true) ?? $products->first();
    $heroImage = $tenant->hero_image_path ? Storage::disk('uploads')->url($tenant->hero_image_path) : data_get($heroProduct, 'image');
    $heroImage2 = $tenant->hero_image_2_path ? Storage::disk('uploads')->url($tenant->hero_image_2_path) : data_get($products->values()->get(1), 'image', $heroImage);
    $heroImage3 = $tenant->hero_image_3_path ? Storage::disk('uploads')->url($tenant->hero_image_3_path) : data_get($products->values()->get(2), 'image', $heroImage);
    $experienceImage = $tenant->experience_image_path ? Storage::disk('uploads')->url($tenant->experience_image_path) : $heroImage2;
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
@elseif($isMimo)
    <section id="top" class="mimo-hero container">
        <div class="mimo-hero-copy">
            <p class="mimo-eyebrow">✦ &nbsp; {{ $content['hero_eyebrow'] }}</p>
            <h1>{{ $content['hero_title'] }} <em>{{ $content['hero_highlight'] }}</em></h1>
            <p>{{ $content['hero_text'] }}</p>
            <div class="mimo-actions"><a class="mimo-primary" href="{{ $content['hero_primary_url'] ?: '#catalogo' }}">{{ $content['hero_primary_label'] }} <span>→</span></a>@if($content['hero_secondary_label'])<a href="{{ $content['hero_secondary_url'] ?: '#catalogo' }}">{{ $content['hero_secondary_label'] }} ↓</a>@endif</div>
            @if($content['show_stats'])<div class="mimo-differentials">@for($i=1;$i<=3;$i++)<span>{{ $i===1?'♡':($i===2?'✦':'⌖') }} <b>{{ $content["stat_{$i}_value"] }}</b> {{ $content["stat_{$i}_label"] }}</span>@endfor</div>@endif
        </div>
        <div class="mimo-collage" aria-label="Seleção de {{ $tenant->name }}">
            <figure class="mimo-photo-main">@if($heroImage)<img src="{{ $heroImage }}" alt="Destaque de {{ $tenant->name }}">@endif<figcaption><strong>{{ $content['hero_note_title'] }}</strong><small>{{ $content['hero_note_text'] }}</small></figcaption></figure>
            <figure class="mimo-photo-small top">@if($heroImage2)<img src="{{ $heroImage2 }}" alt="Produto personalizado">@endif</figure>
            <figure class="mimo-photo-small bottom">@if($heroImage3)<img src="{{ $heroImage3 }}" alt="Produto em destaque">@endif</figure>
            <span class="mimo-heart">♡</span>
        </div>
    </section>
    @if($content['show_marquee'])<div class="mimo-ribbon"><div class="container">@foreach(array_filter(array_map('trim', preg_split('/[•|]+/', $content['marquee']))) as $item)<span>{{ $item }}</span>@if(!$loop->last)<i>◆</i>@endif @endforeach</div></div>@endif
@else
    <section class="catalog-hero container"><div class="hero-copy"><p class="eyebrow">{{ $content['hero_eyebrow'] }}</p><h1>{{ $content['hero_title'] }} {{ $content['hero_highlight'] }}</h1><p>{{ $content['hero_text'] }}</p><a href="#catalogo" class="primary-button">{{ $content['hero_primary_label'] }} <span>↓</span></a></div><div class="hero-composition" aria-hidden="true"><span class="hero-orbit"></span><div class="hero-card hero-card-main"><span>Seleção</span><strong>{{ $products->count() }}</strong><small>produtos disponíveis</small></div><div class="hero-card hero-card-accent">Feito<br>para<br>descobrir.</div></div></section>
@endif

<div id="catalogo" class="container {{ $isAccesso ? 'accesso-catalog-wrap' : ($isMimo ? 'mimo-catalog-wrap' : '') }}">
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

@if($isMimo && $content['show_steps'])
<section id="como-pedir" class="mimo-steps"><div class="container"><p class="mimo-eyebrow">✦ &nbsp; {{ $content['steps_eyebrow'] }}</p><h2>{{ $content['steps_title'] }}</h2><div class="mimo-steps-grid">@for($i=1;$i<=3;$i++)<article><span>0{{ $i }}</span><h3>{{ $content["step_{$i}_title"] }}</h3><p>{{ $content["step_{$i}_text"] }}</p></article>@endfor</div></div></section>
@endif

@if($isMimo && $content['show_experience'])
<section id="sobre" class="mimo-about container"><div class="mimo-about-image">@if($experienceImage)<img src="{{ $experienceImage }}" alt="Sobre {{ $tenant->name }}">@endif<span>♡</span></div><div><p class="mimo-eyebrow">✦ &nbsp; {{ $content['experience_eyebrow'] }}</p><h2>{{ $content['experience_title'] }}</h2><p>{{ $content['experience_text'] }}</p><blockquote>“{{ $content['about_quote'] }}”</blockquote>@if($content['about_button_url'])<a href="{{ $content['about_button_url'] }}" target="_blank" rel="noopener">{{ $content['about_button_label'] }} ↗</a>@endif</div></section>
@endif

@if($isMimo && $content['show_contact'] && $whatsapp)
<section class="mimo-contact"><div class="container"><p>{{ $content['contact_eyebrow'] }}</p><h2>{{ $content['contact_title'] }}</h2><span>{{ $content['contact_text'] }}</span><a href="{{ $whatsapp }}" target="_blank" rel="noopener">{{ $content['contact_button'] }} →</a></div></section>
@endif

@if($isMimo && $content['show_faq'])
<section class="mimo-faq container"><div><p class="mimo-eyebrow">✦ &nbsp; {{ $content['faq_eyebrow'] }}</p><h2>{{ $content['faq_title'] }}</h2></div><div>@for($i=1;$i<=3;$i++)@if($content["faq_{$i}_question"])<details><summary>{{ $content["faq_{$i}_question"] }} <span>+</span></summary><p>{{ $content["faq_{$i}_answer"] }}</p></details>@endif @endfor</div></section>
@endif
@endsection
