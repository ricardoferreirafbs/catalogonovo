@extends('layouts.admin', ['title' => $product->exists ? 'Editar produto' : 'Novo produto', 'eyebrow' => 'Catálogo'])

@section('content')
    <form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="editor-grid">
        @csrf
        @if($product->exists) @method('PUT') @endif
        <section class="panel-card form-panel">
            <div class="form-section-heading"><span>01</span><div><h2>Informações principais</h2><p>Dados usados em todos os modelos de catálogo.</p></div></div>
            <div class="form-grid two-cols">
                <label class="span-2">Nome do produto<input name="name" value="{{ old('name', $product->name) }}" required maxlength="160" placeholder="Ex.: Cadeira Essencial"></label>
                <label>Categoria<select name="category_id"><option value="">Sem categoria</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <label>Código / SKU<input name="sku" value="{{ old('sku', $product->sku) }}" maxlength="80" placeholder="CAD-001"></label>
                <label class="span-2">URL amigável<input name="slug" value="{{ old('slug', $product->slug) }}" maxlength="180" placeholder="gerada-automaticamente"></label>
                <label class="span-2">Descrição<textarea name="description" rows="5" maxlength="5000" placeholder="Materiais, medidas, diferenciais e aplicações...">{{ old('description', $product->description) }}</textarea></label>
            </div>

            <div class="form-section-heading separated"><span>02</span><div><h2>Preço e disponibilidade</h2><p>Informe os valores em reais ou deixe vazio para “Sob consulta”.</p></div></div>
            <div class="form-grid two-cols">
                <label>Preço normal<input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" placeholder="0,00"></label>
                <label>Preço promocional<input type="number" step="0.01" min="0" name="promotional_price" value="{{ old('promotional_price', $product->promotional_price) }}" placeholder="0,00"></label>
                <label>Disponibilidade<input name="stock_label" value="{{ old('stock_label', $product->stock_label) }}" maxlength="80" placeholder="Ex.: Pronta entrega"></label>
                <label>Status<select name="status" required><option value="draft" @selected(old('status', $product->status ?: 'draft') === 'draft')>Rascunho</option><option value="published" @selected(old('status', $product->status) === 'published')>Publicado</option></select></label>
                <label class="check-label span-2"><input type="checkbox" name="featured" value="1" @checked(old('featured', $product->featured))> Exibir como destaque</label>
                <label class="span-2">Mensagem personalizada do WhatsApp<input name="whatsapp_message" value="{{ old('whatsapp_message', $product->whatsapp_message) }}" maxlength="240" placeholder="Olá! Gostaria de saber mais sobre este produto."></label>
            </div>
        </section>

        <aside class="panel-card media-panel">
            <div><p class="eyebrow">Imagem principal</p><h2>Apresentação</h2></div>
            <div class="image-preview" id="image-preview">
                <img src="{{ $product->media->first() ? Storage::disk('public')->url($product->media->first()->path) : asset('images/product-placeholder.svg') }}" alt="Prévia do produto">
            </div>
            <label class="upload-field">Selecionar foto<input id="image-input" type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG ou WebP · até 4 MB</small></label>
            <div class="form-actions"><a href="{{ route('admin.products.index') }}">Cancelar</a><button type="submit" class="primary-button">{{ $product->exists ? 'Salvar alterações' : 'Criar produto' }}</button></div>
        </aside>
    </form>
    <script>
        document.getElementById('image-input')?.addEventListener('change', function () {
            const file = this.files?.[0];
            if (file) document.querySelector('#image-preview img').src = URL.createObjectURL(file);
        });
    </script>
@endsection
