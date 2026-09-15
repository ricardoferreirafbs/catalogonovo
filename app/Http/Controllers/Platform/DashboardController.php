<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $metrics = [
            'tenants' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'products' => Product::count(),
            'users' => User::whereNotNull('tenant_id')->count(),
        ];

        $recentTenants = Tenant::with('owner')
            ->withCount(['products', 'users'])
            ->latest()
            ->limit(8)
            ->get();

        return view('platform.dashboard', compact('metrics', 'recentTenants'));
    }
}
