<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function () {
    Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/produto/{slug}', [CatalogController::class, 'show'])->name('catalog.product');
});

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'create'])->name('login');
    Route::post('/entrar', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/sair', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('painel')->name('admin.')->middleware(['auth', 'tenant.user'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::resource('produtos', ProductController::class)->except('show')->parameters(['produtos' => 'product'])->names('products');
    Route::get('/estrutura', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categorias', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/menus', [MenuItemController::class, 'store'])->name('menus.store');
    Route::put('/menus/{menuItem}', [MenuItemController::class, 'update'])->name('menus.update');
    Route::delete('/menus/{menuItem}', [MenuItemController::class, 'destroy'])->name('menus.destroy');
    Route::get('/conteudo', [ContentController::class, 'edit'])->name('content.edit');
    Route::put('/conteudo', [ContentController::class, 'update'])->name('content.update');
    Route::get('/aparencia', [ThemeController::class, 'edit'])->name('theme.edit');
    Route::put('/aparencia', [ThemeController::class, 'update'])->name('theme.update');
});

Route::prefix('plataforma')->name('platform.')->middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/', PlatformDashboardController::class)->name('dashboard');
    Route::patch('/empresas/{tenant}/status', [TenantController::class, 'status'])->name('tenants.status');
    Route::resource('empresas', TenantController::class)->except('show')->parameters(['empresas' => 'tenant'])->names('tenants');
});
