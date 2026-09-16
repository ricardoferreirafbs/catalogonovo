@extends('layouts.admin', ['title' => 'Aparência', 'eyebrow' => 'Identidade visual'])

@section('content')
    @php
        $theme = $tenant->theme ?? [];
        $selectedTemplate = data_get($theme, 'template', 'classic');
        $selectedPalette = $palettes[$selectedTemplate] ?? $palettes['classic'];
    @endphp
    <form action="{{ route('admin.theme.update') }}" method="post" class="theme-editor" id="theme-form">
        @csrf @method('PUT')
        <section class="panel-card form-panel">
            <div class="form-section-heading"><span>00</span><div><h2>Template</h2><p>Escolha a base visual. A prévia e as cores originais mudam na hora.</p></div></div>
            <div class="template-options">
                <label class="template-option"><input type="radio" name="template" value="mimo" @checked($selectedTemplate === 'mimo')><span><i class="template-swatches" style="--swatch-a:#7C2944;--swatch-b:#D79A9B;--swatch-c:#FFF9F5"></i><strong>Aurora</strong><small>Suave, afetivo e artesanal.</small></span></label>
                <label class="template-option"><input type="radio" name="template" value="accesso" @checked($selectedTemplate === 'accesso')><span><i class="template-swatches" style="--swatch-a:#3478D4;--swatch-b:#FFD51F;--swatch-c:#FBFAF6"></i><strong>Vértice</strong><small>Marcante, editorial e contemporâneo.</small></span></label>
                <label class="template-option"><input type="radio" name="template" value="classic" @checked($selectedTemplate === 'classic')><span><i class="template-swatches" style="--swatch-a:#173F35;--swatch-b:#E48A4A;--swatch-c:#F4F6F3"></i><strong>Nítido</strong><small>Limpo, objetivo e organizado.</small></span></label>
                <label class="template-option"><input type="radio" name="template" value="prisma" @checked($selectedTemplate === 'prisma')><span><i class="template-swatches" style="--swatch-a:#00B889;--swatch-b:#7C5CFF;--swatch-c:#07151B"></i><strong>Prisma</strong><small>Técnico, ousado e multissetorial.</small></span></label>
                <label class="template-option"><input type="radio" name="template" value="impeto" @checked($selectedTemplate === 'impeto')><span><i class="template-swatches" style="--swatch-a:#D51F2B;--swatch-b:#F0B429;--swatch-c:#111214"></i><strong>Ímpeto</strong><small>Automotivo, potente e premium.</small></span></label>
                <label class="template-option"><input type="radio" name="template" value="aurea" @checked($selectedTemplate === 'aurea')><span><i class="template-swatches" style="--swatch-a:#B28A45;--swatch-b:#E4C98D;--swatch-c:#1C1813"></i><strong>Áurea</strong><small>Joalheria elegante e sofisticada.</small></span></label>
            </div>
            <div class="form-section-heading"><span>01</span><div><h2>Cores da marca</h2><p>Comece com a paleta original e personalize quando quiser.</p></div></div>
            <div class="color-grid">
                @foreach (['primary' => 'Principal', 'accent' => 'Destaque', 'surface' => 'Fundo', 'dark' => 'Escura'] as $field => $label)
                    @php($color = old($field, data_get($theme, $field, $selectedPalette[$field])))
                    <label>{{ $label }}<div class="color-field"><input type="color" name="{{ $field }}" value="{{ $color }}"><code>{{ strtoupper($color) }}</code></div></label>
                @endforeach
            </div>
            <button class="secondary-button palette-reset" type="button" id="restore-template-colors">Restaurar cores originais</button>
            <div class="form-section-heading separated"><span>02</span><div><h2>Capa do catálogo</h2><p>Mensagem principal exibida aos visitantes.</p></div></div>
            <div class="form-grid">
                <label>Título<input name="hero_title" required maxlength="90" value="{{ old('hero_title', data_get($theme, 'hero_title', 'Sua coleção em destaque.')) }}"></label>
                <label>Texto<textarea name="hero_text" rows="3" maxlength="240">{{ old('hero_text', data_get($theme, 'hero_text', 'Produtos escolhidos para tornar cada momento especial.')) }}</textarea></label>
                <div class="two-cols form-grid">
                    <label>Tipografia<select name="font_style"><option value="modern" @selected(data_get($theme, 'font_style', 'modern') === 'modern')>Moderna</option><option value="classic" @selected(data_get($theme, 'font_style') === 'classic')>Clássica</option><option value="technical" @selected(data_get($theme, 'font_style') === 'technical')>Técnica</option></select></label>
                    <label>Formato dos cards<select name="card_style"><option value="soft" @selected(data_get($theme, 'card_style', 'soft') === 'soft')>Suave</option><option value="square" @selected(data_get($theme, 'card_style') === 'square')>Reto</option><option value="outline" @selected(data_get($theme, 'card_style') === 'outline')>Contornado</option></select></label>
                </div>
            </div>
            <button class="primary-button" type="submit">Publicar aparência</button>
        </section>

        <aside class="theme-preview" id="theme-preview" data-template="{{ $selectedTemplate }}" data-font="{{ data_get($theme, 'font_style', 'modern') }}" data-card="{{ data_get($theme, 'card_style', 'soft') }}" style="--preview-brand:{{ data_get($theme, 'primary', $selectedPalette['primary']) }};--preview-accent:{{ data_get($theme, 'accent', $selectedPalette['accent']) }};--preview-surface:{{ data_get($theme, 'surface', $selectedPalette['surface']) }};--preview-dark:{{ data_get($theme, 'dark', $selectedPalette['dark']) }}">
            <div class="preview-browser"><span></span><span></span><span></span><b id="preview-template-name">{{ ['mimo' => 'Aurora', 'accesso' => 'Vértice', 'classic' => 'Nítido', 'prisma' => 'Prisma', 'impeto' => 'Ímpeto', 'aurea' => 'Áurea'][$selectedTemplate] }}</b></div>
            <div class="preview-site">
                <header class="preview-nav"><strong><i>{{ mb_substr($tenant->name, 0, 1) }}</i>{{ $tenant->name }}</strong><nav><span></span><span></span><span></span></nav><button>Contato</button></header>
                <main class="preview-hero-stage">
                    <div class="preview-hero-copy"><small>COLEÇÃO ESPECIAL</small><h2 class="preview-live-title">{{ data_get($theme, 'hero_title', 'Sua coleção em destaque.') }}</h2><p class="preview-live-text">{{ data_get($theme, 'hero_text', 'Produtos escolhidos para tornar cada momento especial.') }}</p><div><button>Explorar coleção</button><a>Saiba mais →</a></div></div>
                    <div class="preview-art preview-art-mimo"><i class="art-main"></i><i class="art-small top"></i><i class="art-small bottom"></i><b>♥</b></div>
                    <div class="preview-art preview-art-accesso"><i class="art-orbit"></i><i class="art-disc"></i><i class="art-portrait"></i><b>feito para você</b></div>
                    <div class="preview-art preview-art-classic"><i class="classic-shape one"></i><i class="classic-shape two"></i><i class="classic-shape three"></i><b>01</b></div>
                    <div class="preview-art preview-art-prisma"><i class="prisma-grid"></i><i class="prisma-core"></i><i class="prisma-panel"></i><b>SYS / 01</b></div>
                    <div class="preview-art preview-art-impeto"><i class="impeto-road"></i><i class="impeto-car"></i><i class="impeto-light"></i><b>PERFORMANCE</b></div>
                    <div class="preview-art preview-art-aurea"><i class="aurea-halo"></i><i class="aurea-gem"></i><i class="aurea-pedestal"></i><b>◆</b></div>
                </main>
                <div class="preview-ribbon"><span>EXCLUSIVIDADE</span><i>◆</i><span>QUALIDADE</span><i>◆</i><span>FEITO PARA VOCÊ</span></div>
                <section class="preview-catalog"><small>NOSSO CATÁLOGO</small><h3>Escolhas que inspiram</h3><div class="preview-products">@for ($i = 0; $i < 3; $i++)<article><i></i><span></span><b></b></article>@endfor</div></section>
            </div>
        </aside>
    </form>
    <script>
        (() => {
            const form = document.getElementById('theme-form');
            const preview = document.getElementById('theme-preview');
            const palettes = @json($palettes);
            const templateNames = { mimo: 'Aurora', accesso: 'Vértice', classic: 'Nítido', prisma: 'Prisma', impeto: 'Ímpeto', aurea: 'Áurea' };
            const properties = { primary: '--preview-brand', accent: '--preview-accent', surface: '--preview-surface', dark: '--preview-dark' };
            const syncColor = input => {
                input.nextElementSibling.textContent = input.value.toUpperCase();
                preview.style.setProperty(properties[input.name], input.value);
            };
            const applyOriginalPalette = template => Object.entries(palettes[template]).forEach(([name, value]) => {
                const input = form.elements[name];
                input.value = value;
                syncColor(input);
            });
            const selectTemplate = (template, applyPalette = false) => {
                preview.dataset.template = template;
                document.getElementById('preview-template-name').textContent = templateNames[template];
                if (applyPalette) applyOriginalPalette(template);
            };
            form.querySelectorAll('input[type=color]').forEach(input => input.addEventListener('input', () => syncColor(input)));
            form.querySelectorAll('input[name=template]').forEach(input => input.addEventListener('change', () => selectTemplate(input.value, true)));
            document.getElementById('restore-template-colors').addEventListener('click', () => applyOriginalPalette(form.elements.template.value));
            form.hero_title.addEventListener('input', event => preview.querySelector('.preview-live-title').textContent = event.target.value || 'Sua coleção em destaque.');
            form.hero_text.addEventListener('input', event => preview.querySelector('.preview-live-text').textContent = event.target.value);
            form.font_style.addEventListener('change', event => preview.dataset.font = event.target.value);
            form.card_style.addEventListener('change', event => preview.dataset.card = event.target.value);
        })();
    </script>
@endsection
