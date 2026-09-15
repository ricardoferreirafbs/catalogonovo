@extends('layouts.admin', ['title' => 'Aparência', 'eyebrow' => 'Identidade visual'])

@section('content')
    @php($theme = $tenant->theme ?? [])
    <form action="{{ route('admin.theme.update') }}" method="post" class="theme-editor" id="theme-form">
        @csrf @method('PUT')
        <section class="panel-card form-panel">
            <div class="form-section-heading"><span>00</span><div><h2>Template</h2><p>Escolha a base visual do catálogo. O conteúdo permanece o mesmo.</p></div></div>
            <div class="template-options">
                <label class="template-option"><input type="radio" name="template" value="accesso" @checked(data_get($theme, 'template', 'classic') === 'accesso')><span><strong>Editorial Acesso</strong><small>Hero marcante, faixa de destaques e seções comerciais.</small></span></label>
                <label class="template-option"><input type="radio" name="template" value="classic" @checked(data_get($theme, 'template', 'classic') === 'classic')><span><strong>Catálogo Essencial</strong><small>Layout original, direto e minimalista.</small></span></label>
            </div>
            <div class="form-section-heading"><span>01</span><div><h2>Cores da marca</h2><p>Aplicadas automaticamente em todo o catálogo.</p></div></div>
            <div class="color-grid">
                <label>Principal<div class="color-field"><input type="color" name="primary" value="{{ old('primary', data_get($theme, 'primary', '#173f35')) }}"><code>{{ old('primary', data_get($theme, 'primary', '#173f35')) }}</code></div></label>
                <label>Destaque<div class="color-field"><input type="color" name="accent" value="{{ old('accent', data_get($theme, 'accent', '#e48a4a')) }}"><code>{{ old('accent', data_get($theme, 'accent', '#e48a4a')) }}</code></div></label>
                <label>Fundo<div class="color-field"><input type="color" name="surface" value="{{ old('surface', data_get($theme, 'surface', '#f4f6f3')) }}"><code>{{ old('surface', data_get($theme, 'surface', '#f4f6f3')) }}</code></div></label>
                <label>Escura<div class="color-field"><input type="color" name="dark" value="{{ old('dark', data_get($theme, 'dark', '#111a35')) }}"><code>{{ old('dark', data_get($theme, 'dark', '#111a35')) }}</code></div></label>
            </div>
            <div class="form-section-heading separated"><span>02</span><div><h2>Capa do catálogo</h2><p>Mensagem principal exibida aos visitantes.</p></div></div>
            <div class="form-grid">
                <label>Título<input name="hero_title" required maxlength="90" value="{{ old('hero_title', data_get($theme, 'hero_title')) }}"></label>
                <label>Texto<textarea name="hero_text" rows="3" maxlength="240">{{ old('hero_text', data_get($theme, 'hero_text')) }}</textarea></label>
                <div class="two-cols form-grid">
                    <label>Tipografia<select name="font_style"><option value="modern" @selected(data_get($theme, 'font_style') === 'modern')>Moderna</option><option value="classic" @selected(data_get($theme, 'font_style') === 'classic')>Clássica</option><option value="technical" @selected(data_get($theme, 'font_style') === 'technical')>Técnica</option></select></label>
                    <label>Formato dos cards<select name="card_style"><option value="soft" @selected(data_get($theme, 'card_style') === 'soft')>Suave</option><option value="square" @selected(data_get($theme, 'card_style') === 'square')>Reto</option><option value="outline" @selected(data_get($theme, 'card_style') === 'outline')>Contornado</option></select></label>
                </div>
            </div>
            <button class="primary-button" type="submit">Publicar aparência</button>
        </section>
        <aside class="theme-preview" id="theme-preview" style="--preview-brand: {{ data_get($theme, 'primary', '#173f35') }}; --preview-accent: {{ data_get($theme, 'accent', '#e48a4a') }}; --preview-surface: {{ data_get($theme, 'surface', '#f4f6f3') }}">
            <div class="preview-browser"><span></span><span></span><span></span></div>
            <div class="preview-nav"><strong>{{ $tenant->name }}</strong><i></i></div>
            <div class="preview-hero"><small>CATÁLOGO</small><h2>{{ data_get($theme, 'hero_title', 'Sua coleção em destaque.') }}</h2><p>{{ data_get($theme, 'hero_text') }}</p><button>Explorar coleção</button></div>
            <div class="preview-products"><span></span><span></span><span></span></div>
        </aside>
    </form>
    <script>
        const form = document.getElementById('theme-form');
        const preview = document.getElementById('theme-preview');
        form?.querySelectorAll('input[type=color]').forEach(input => input.addEventListener('input', () => {
            input.nextElementSibling.textContent = input.value;
            preview.style.setProperty(input.name === 'primary' ? '--preview-brand' : input.name === 'accent' ? '--preview-accent' : '--preview-surface', input.value);
        }));
        form?.hero_title?.addEventListener('input', e => preview.querySelector('h2').textContent = e.target.value);
        form?.hero_text?.addEventListener('input', e => preview.querySelector('.preview-hero p').textContent = e.target.value);
    </script>
@endsection
