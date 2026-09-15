@extends('layouts.admin', ['title' => 'Estrutura', 'eyebrow' => 'Navegação e organização'])

@section('content')
    <div class="structure-grid">
        <section class="panel-card form-panel">
            <div class="panel-heading"><div><p class="eyebrow">Categorias</p><h2>Até quatro níveis</h2><p>Crie categorias, subcategorias e escolha quais aparecem na navegação principal.</p></div></div>
            <form action="{{ route('admin.categories.store') }}" method="post" class="form-grid two-cols compact-form">
                @csrf
                <label>Nome<input name="name" required maxlength="100" placeholder="Ex.: Coleções"></label>
                <label>Categoria superior<select name="parent_id"><option value="">Nível principal</option>@foreach($categories as $option)<option value="{{ $option->id }}">{{ str_repeat('— ', $option->depth() - 1) }}{{ $option->name }}</option>@endforeach</select></label>
                <label class="span-2">Descrição<textarea name="description" rows="2" maxlength="500" placeholder="Texto opcional para contextualizar a categoria"></textarea></label>
                <label class="check-label"><input type="checkbox" name="is_active" value="1" checked> Categoria ativa</label>
                <label class="check-label"><input type="checkbox" name="show_in_menu" value="1"> Usar como menu principal</label>
                <button class="primary-button span-2" type="submit">Adicionar categoria</button>
            </form>

            <div class="builder-list">
                @forelse($categories as $category)
                    <details class="builder-item">
                        <summary><span class="level-pill">N{{ $category->depth() }}</span><div><strong>{{ $category->breadcrumbName() }}</strong><small>{{ $category->products->count() }} produtos · {{ $category->is_active ? 'Ativa' : 'Oculta' }}{{ $category->show_in_menu ? ' · Menu principal' : '' }}</small></div><span>Editar</span></summary>
                        <form action="{{ route('admin.categories.update', $category) }}" method="post" class="form-grid two-cols builder-form">
                            @csrf @method('PUT')
                            <label>Nome<input name="name" value="{{ $category->name }}" required></label>
                            <label>Identificador<input name="slug" value="{{ $category->slug }}" required></label>
                            <label>Categoria superior<select name="parent_id"><option value="">Nível principal</option>@foreach($categories->where('id', '!=', $category->id) as $option)<option value="{{ $option->id }}" @selected($category->parent_id === $option->id)>{{ str_repeat('— ', $option->depth() - 1) }}{{ $option->name }}</option>@endforeach</select></label>
                            <label>Ordem<input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0"></label>
                            <label class="span-2">Descrição<textarea name="description" rows="2">{{ $category->description }}</textarea></label>
                            <label class="check-label"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Ativa</label>
                            <label class="check-label"><input type="checkbox" name="show_in_menu" value="1" @checked($category->show_in_menu)> Menu principal</label>
                            <button class="secondary-button" type="submit">Salvar categoria</button>
                        </form>
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="post" class="inline-delete" onsubmit="return confirm('Excluir esta categoria? Os produtos não serão excluídos.')">@csrf @method('DELETE')<button type="submit">Excluir</button></form>
                    </details>
                @empty
                    <div class="empty-inline">Nenhuma categoria cadastrada.</div>
                @endforelse
            </div>
        </section>

        <aside class="panel-card form-panel">
            <div class="panel-heading"><div><p class="eyebrow">Menus livres</p><h2>Links adicionais</h2><p>Inclua seções da página, páginas internas ou links externos.</p></div></div>
            <form action="{{ route('admin.menus.store') }}" method="post" class="form-grid compact-form">
                @csrf
                <label>Rótulo<input name="label" required maxlength="60" placeholder="Ex.: Sobre nós"></label>
                <label>Destino<input name="url" required maxlength="500" placeholder="#experiencia ou https://..."></label>
                <label class="check-label"><input type="checkbox" name="is_active" value="1" checked> Item ativo</label>
                <label class="check-label"><input type="checkbox" name="open_new_tab" value="1"> Abrir em nova aba</label>
                <button class="primary-button" type="submit">Adicionar menu</button>
            </form>
            <div class="builder-list">
                @foreach($tenant->menuItems()->orderBy('sort_order')->get() as $item)
                    <details class="builder-item">
                        <summary><div><strong>{{ $item->label }}</strong><small>{{ $item->url }} · {{ $item->is_active ? 'Ativo' : 'Oculto' }}</small></div><span>Editar</span></summary>
                        <form action="{{ route('admin.menus.update', $item) }}" method="post" class="form-grid builder-form">@csrf @method('PUT')
                            <label>Rótulo<input name="label" value="{{ $item->label }}" required></label>
                            <label>Destino<input name="url" value="{{ $item->url }}" required></label>
                            <label>Ordem<input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0"></label>
                            <label class="check-label"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Ativo</label>
                            <label class="check-label"><input type="checkbox" name="open_new_tab" value="1" @checked($item->open_new_tab)> Nova aba</label>
                            <button class="secondary-button" type="submit">Salvar menu</button>
                        </form>
                        <form action="{{ route('admin.menus.destroy', $item) }}" method="post" class="inline-delete">@csrf @method('DELETE')<button type="submit">Excluir</button></form>
                    </details>
                @endforeach
            </div>
        </aside>
    </div>
@endsection
