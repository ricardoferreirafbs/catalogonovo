<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('categories')->where('tenant_id', $tenant->id)],
        ]);
        $tenant->categories()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'sort_order' => $tenant->categories()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Categoria adicionada.');
    }

    public function destroy(Category $category)
    {
        abort_unless($category->tenant_id === auth()->user()->tenant_id, 404);
        $category->delete();

        return back()->with('success', 'Categoria excluída.');
    }
}
