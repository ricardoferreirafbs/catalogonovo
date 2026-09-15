<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Tenant;
use App\Http\Controllers\Admin\ContentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $categories = $tenant->categories()->with('parent')->where('is_active', true)->orderBy('sort_order')->get();
        $menuCategories = $categories->where('show_in_menu', true);
        $menuItems = $tenant->menuItems()->where('is_active', true)->orderBy('sort_order')->get();
        $content = array_replace_recursive(ContentController::defaults(), $tenant->content ?? []);
        $products = $tenant->products()
            ->with(['category', 'media'])
            ->where('status', 'published')
            ->latest('featured')
            ->latest()
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'description' => $product->description,
                'price' => $product->price ? (float) $product->price : null,
                'promotional_price' => $product->promotional_price ? (float) $product->promotional_price : null,
                'category_id' => $product->category_id,
                'category' => $product->category?->name,
                'category_slug' => $product->category?->slug,
                'featured' => $product->featured,
                'stock_label' => $product->stock_label,
                'image' => $product->media->first()
                    ? Storage::disk('uploads')->url($product->media->first()->path)
                    : asset('images/product-placeholder.svg'),
                'url' => route('catalog.product', $product->slug),
            ]);

        return view('catalog.index', compact('tenant', 'categories', 'menuCategories', 'menuItems', 'products', 'content'));
    }

    public function show(Request $request, string $slug)
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $product = $tenant->products()
            ->with(['category', 'media'])
            ->where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();

        $content = array_replace_recursive(ContentController::defaults(), $tenant->content ?? []);
        $menuCategories = $tenant->categories()->where('is_active', true)->where('show_in_menu', true)->orderBy('sort_order')->get();
        $menuItems = $tenant->menuItems()->where('is_active', true)->orderBy('sort_order')->get();

        return view('catalog.show', compact('tenant', 'product', 'content', 'menuCategories', 'menuItems'));
    }
}
