<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;
        $categories = $tenant->categories()->with(['parent', 'products'])->orderBy('sort_order')->get();
        return view('admin.categories.index', compact('tenant', 'categories'));
    }

    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $data = $this->validated($request, $tenant->id);
        $parent = $this->parentForTenant($data['parent_id'] ?? null, $tenant->id);
        if ($parent && $parent->depth() >= 4) {
            throw ValidationException::withMessages(['parent_id' => 'O limite é de quatro níveis de categorias.']);
        }
        $tenant->categories()->create([
            ...$data, 'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']), 'parent_id' => $parent?->id,
            'show_in_menu' => $request->boolean('show_in_menu'), 'is_active' => $request->boolean('is_active', true),
            'sort_order' => $tenant->categories()->max('sort_order') + 1,
        ]);
        return back()->with('success', 'Categoria adicionada.');
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeTenant($category);
        $data = $this->validated($request, $category->tenant_id, $category->id);
        $parent = $this->parentForTenant($data['parent_id'] ?? null, $category->tenant_id);
        if ($parent && ($parent->id === $category->id || $this->isDescendant($parent, $category))) {
            throw ValidationException::withMessages(['parent_id' => 'Uma categoria não pode ser filha dela mesma.']);
        }
        if ($parent && ($parent->depth() + $this->subtreeHeight($category)) > 4) {
            throw ValidationException::withMessages(['parent_id' => 'Essa alteração ultrapassa o limite de quatro níveis.']);
        }
        $category->update([
            ...$data, 'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']), 'parent_id' => $parent?->id,
            'show_in_menu' => $request->boolean('show_in_menu'), 'is_active' => $request->boolean('is_active'),
        ]);
        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroy(Category $category)
    {
        $this->authorizeTenant($category);
        $category->delete();
        return back()->with('success', 'Categoria excluída. As subcategorias foram movidas para o nível principal.');
    }

    private function validated(Request $request, int $tenantId, ?int $categoryId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('categories')->where('tenant_id', $tenantId)->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'], 'show_in_menu' => ['nullable', 'boolean'],
        ]);
    }

    private function parentForTenant(?int $id, int $tenantId): ?Category
    {
        return $id ? Category::where('tenant_id', $tenantId)->findOrFail($id) : null;
    }

    private function authorizeTenant(Category $category): void
    {
        abort_unless($category->tenant_id === auth()->user()->tenant_id, 404);
    }

    private function isDescendant(Category $candidate, Category $category): bool
    {
        $parent = $candidate;
        while ($parent) { if ($parent->id === $category->id) return true; $parent = $parent->parent; }
        return false;
    }

    private function subtreeHeight(Category $category): int
    {
        $children = $category->children;
        return $children->isEmpty() ? 1 : 1 + $children->max(fn (Category $child) => $this->subtreeHeight($child));
    }
}
