<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $tenant = auth()->user()->tenant;
        abort_unless($tenant, 403);

        $metrics = [
            'products' => $tenant->products()->count(),
            'published' => $tenant->products()->where('status', 'published')->count(),
            'categories' => $tenant->categories()->count(),
            'featured' => $tenant->products()->where('featured', true)->count(),
        ];
        $recentProducts = $tenant->products()->with('category')->latest()->limit(6)->get();

        return view('admin.dashboard', compact('tenant', 'metrics', 'recentProducts'));
    }
}
