<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
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

Route::prefix('painel')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::resource('produtos', ProductController::class)->except('show')->parameters(['produtos' => 'product'])->names('products');
    Route::post('/categorias', [CategoryController::class, 'store'])->name('categories.store');
    Route::delete('/categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::get('/aparencia', [ThemeController::class, 'edit'])->name('theme.edit');
    Route::put('/aparencia', [ThemeController::class, 'update'])->name('theme.update');
});
