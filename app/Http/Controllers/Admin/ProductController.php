<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;
        $products = $tenant->products()->with(['category', 'media'])->latest()->paginate(12);

        return view('admin.products.index', compact('tenant', 'products'));
    }

    public function create()
    {
        $tenant = auth()->user()->tenant;
        $categories = $tenant->categories()->orderBy('sort_order')->get();

        return view('admin.products.form', ['product' => new Product, 'categories' => $categories]);
    }

    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $data = $this->validated($request, $tenant->id);
        $data['tenant_id'] = $tenant->id;
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['featured'] = $request->boolean('featured');
        $product = Product::create($data);
        $this->storeImage($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Produto criado com sucesso.');
    }

    public function edit(Product $product)
    {
        $this->authorizeTenant($product);
        $categories = auth()->user()->tenant->categories()->orderBy('sort_order')->get();

        return view('admin.products.form', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeTenant($product);
        $data = $this->validated($request, $product->tenant_id, $product->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['featured'] = $request->boolean('featured');
        $product->update($data);
        $this->storeImage($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Produto atualizado.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeTenant($product);
        foreach ($product->media as $media) {
            Storage::disk('public')->delete($media->path);
        }
        $product->delete();

        return back()->with('success', 'Produto excluído.');
    }

    private function validated(Request $request, int $tenantId, ?int $productId = null): array
    {
        return $request->validate([
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'alpha_dash', 'max:180', Rule::unique('products')->where('tenant_id', $tenantId)->ignore($productId)],
            'sku' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'promotional_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'stock_label' => ['nullable', 'string', 'max:80'],
            'whatsapp_message' => ['nullable', 'string', 'max:240'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function storeImage(Request $request, Product $product): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        foreach ($product->media as $media) {
            Storage::disk('public')->delete($media->path);
            $media->delete();
        }
        $path = $request->file('image')->store("tenants/{$product->tenant_id}/products", 'public');
        $product->media()->create(['path' => $path, 'alt_text' => $product->name]);
    }

    private function authorizeTenant(Product $product): void
    {
        abort_unless($product->tenant_id === auth()->user()->tenant_id, 404);
    }
}
