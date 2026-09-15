@extends('layouts.admin', ['title' => 'Conteúdo', 'eyebrow' => 'Editor de todas as áreas'])

@section('content')
<form action="{{ route('admin.content.update') }}" method="post" enctype="multipart/form-data" class="content-editor">
    @csrf @method('PUT')
    <div class="content-editor-head panel-card">
        <div><p class="eyebrow">Template publicado</p><h2>Personalize textos, imagens e seções</h2><p>As alterações são aplicadas somente ao catálogo da sua empresa.</p></div>
        <div class="form-actions"><a href="{{ $tenant->catalogUrl() }}" target="_blank">Visualizar catálogo ↗</a><button class="primary-button" type="submit">Publicar conteúdo</button></div>
    </div>

    <details class="panel-card editor-section" open>
        <summary><span>01</span><div><strong>Marca e SEO</strong><small>Logo, imagem principal, contato e dados para buscadores.</small></div></summary>
        <div class="form-grid two-cols editor-section-body">
            <label>Logo<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><small>PNG, JPG ou WebP · até 2 MB</small></label>
            <label>Imagem principal<input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"><small>Preferencialmente vertical · até 6 MB</small></label>
            <label>WhatsApp / telefone<input name="contact_phone" value="{{ old('contact_phone', $tenant->contact_phone) }}" placeholder="5511999999999"></label>
            <label>Título para Google<input name="seo_title" value="{{ old('seo_title', $content['seo_title']) }}" maxlength="70"></label>
            <label class="span-2">Descrição para Google<textarea name="seo_description" rows="2" maxlength="170">{{ old('seo_description', $content['seo_description']) }}</textarea></label>
        </div>
    </details>

    <details class="panel-card editor-section" open>
        <summary><span>02</span><div><strong>Capa principal</strong><small>Primeira mensagem, chamadas e indicadores.</small></div></summary>
        <div class="form-grid two-cols editor-section-body">
            <label>Chamada superior<input name="hero_eyebrow" value="{{ old('hero_eyebrow', $content['hero_eyebrow']) }}"></label>
            <label>Título destacado<input name="hero_highlight" value="{{ old('hero_highlight', $content['hero_highlight']) }}"></label>
            <label class="span-2">Título principal<input name="hero_title" required value="{{ old('hero_title', $content['hero_title']) }}"></label>
            <label class="span-2">Descrição<textarea name="hero_text" rows="3">{{ old('hero_text', $content['hero_text']) }}</textarea></label>
            <label>Botão principal<input name="hero_primary_label" value="{{ old('hero_primary_label', $content['hero_primary_label']) }}"></label><label>Destino<input name="hero_primary_url" value="{{ old('hero_primary_url', $content['hero_primary_url']) }}"></label>
            <label>Botão secundário<input name="hero_secondary_label" value="{{ old('hero_secondary_label', $content['hero_secondary_label']) }}"></label><label>Destino<input name="hero_secondary_url" value="{{ old('hero_secondary_url', $content['hero_secondary_url']) }}"></label>
            <label class="check-label span-2"><input type="checkbox" name="show_stats" value="1" @checked(old('show_stats', $content['show_stats']))> Exibir indicadores</label>
            @for($i=1;$i<=3;$i++)<label>Indicador {{ $i }} — valor<input name="stat_{{ $i }}_value" value="{{ old("stat_{$i}_value", $content["stat_{$i}_value"]) }}"></label><label>Indicador {{ $i }} — legenda<input name="stat_{{ $i }}_label" value="{{ old("stat_{$i}_label", $content["stat_{$i}_label"]) }}"></label>@endfor
            <label class="check-label"><input type="checkbox" name="show_marquee" value="1" @checked(old('show_marquee', $content['show_marquee']))> Exibir faixa de destaques</label>
            <label>Texto da faixa<input name="marquee" value="{{ old('marquee', $content['marquee']) }}"></label>
        </div>
    </details>

    <details class="panel-card editor-section">
        <summary><span>03</span><div><strong>Catálogo e experiência</strong><small>Títulos da vitrine e bloco institucional.</small></div></summary>
        <div class="form-grid two-cols editor-section-body">
            <label>Chamada do catálogo<input name="catalog_eyebrow" value="{{ old('catalog_eyebrow', $content['catalog_eyebrow']) }}"></label>
            <label>Título do catálogo<input name="catalog_title" value="{{ old('catalog_title', $content['catalog_title']) }}"></label>
            <label class="span-2">Texto do catálogo<textarea name="catalog_text" rows="2">{{ old('catalog_text', $content['catalog_text']) }}</textarea></label>
            <label class="check-label span-2"><input type="checkbox" name="show_experience" value="1" @checked(old('show_experience', $content['show_experience']))> Exibir seção de experiência</label>
            <label>Chamada<input name="experience_eyebrow" value="{{ old('experience_eyebrow', $content['experience_eyebrow']) }}"></label><label>Título<input name="experience_title" value="{{ old('experience_title', $content['experience_title']) }}"></label>
            <label class="span-2">Texto<textarea name="experience_text" rows="3">{{ old('experience_text', $content['experience_text']) }}</textarea></label>
            @for($i=1;$i<=3;$i++)<label>Diferencial {{ $i }}<input name="experience_item_{{ $i }}_title" value="{{ old("experience_item_{$i}_title", $content["experience_item_{$i}_title"]) }}"></label><label>Descrição<textarea name="experience_item_{{ $i }}_text" rows="2">{{ old("experience_item_{$i}_text", $content["experience_item_{$i}_text"]) }}</textarea></label>@endfor
        </div>
    </details>

    <details class="panel-card editor-section">
        <summary><span>04</span><div><strong>Como funciona e contato</strong><small>Jornada do cliente e chamada para atendimento.</small></div></summary>
        <div class="form-grid two-cols editor-section-body">
            <label class="check-label span-2"><input type="checkbox" name="show_steps" value="1" @checked(old('show_steps', $content['show_steps']))> Exibir jornada</label>
            <label>Chamada<input name="steps_eyebrow" value="{{ old('steps_eyebrow', $content['steps_eyebrow']) }}"></label><label>Título<input name="steps_title" value="{{ old('steps_title', $content['steps_title']) }}"></label>
            @for($i=1;$i<=3;$i++)<label>Passo {{ $i }}<input name="step_{{ $i }}_title" value="{{ old("step_{$i}_title", $content["step_{$i}_title"]) }}"></label><label>Descrição<textarea name="step_{{ $i }}_text" rows="2">{{ old("step_{$i}_text", $content["step_{$i}_text"]) }}</textarea></label>@endfor
            <label class="check-label span-2"><input type="checkbox" name="show_contact" value="1" @checked(old('show_contact', $content['show_contact']))> Exibir chamada de contato</label>
            <label>Chamada<input name="contact_eyebrow" value="{{ old('contact_eyebrow', $content['contact_eyebrow']) }}"></label><label>Título<input name="contact_title" value="{{ old('contact_title', $content['contact_title']) }}"></label>
            <label class="span-2">Texto<textarea name="contact_text" rows="2">{{ old('contact_text', $content['contact_text']) }}</textarea></label><label>Texto do botão<input name="contact_button" value="{{ old('contact_button', $content['contact_button']) }}"></label>
        </div>
    </details>

    <details class="panel-card editor-section">
        <summary><span>05</span><div><strong>Perguntas e rodapé</strong><small>Dúvidas frequentes e mensagem final.</small></div></summary>
        <div class="form-grid two-cols editor-section-body">
            <label class="check-label span-2"><input type="checkbox" name="show_faq" value="1" @checked(old('show_faq', $content['show_faq']))> Exibir perguntas frequentes</label>
            <label>Chamada<input name="faq_eyebrow" value="{{ old('faq_eyebrow', $content['faq_eyebrow']) }}"></label><label>Título<input name="faq_title" value="{{ old('faq_title', $content['faq_title']) }}"></label>
            @for($i=1;$i<=3;$i++)<label>Pergunta {{ $i }}<input name="faq_{{ $i }}_question" value="{{ old("faq_{$i}_question", $content["faq_{$i}_question"]) }}"></label><label>Resposta<textarea name="faq_{{ $i }}_answer" rows="2">{{ old("faq_{$i}_answer", $content["faq_{$i}_answer"]) }}</textarea></label>@endfor
            <label class="span-2">Texto do rodapé<textarea name="footer_text" rows="2">{{ old('footer_text', $content['footer_text']) }}</textarea></label>
        </div>
    </details>
    <div class="sticky-save"><span>Revise as alterações antes de publicar.</span><button class="primary-button" type="submit">Publicar conteúdo</button></div>
</form>
@endsection
