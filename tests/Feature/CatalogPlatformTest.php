<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_only_exposes_products_from_resolved_tenant(): void
    {
        $aurora = Tenant::create(['name' => 'Aurora', 'slug' => 'aurora', 'status' => 'active']);
        $other = Tenant::create(['name' => 'Outra', 'slug' => 'outra', 'status' => 'active']);
        Product::create(['tenant_id' => $aurora->id, 'name' => 'Produto Aurora', 'slug' => 'produto-aurora', 'status' => 'published']);
        Product::create(['tenant_id' => $other->id, 'name' => 'Produto Sigiloso', 'slug' => 'produto-sigiloso', 'status' => 'published']);

        $response = $this->withServerVariables(['HTTP_HOST' => 'aurora.catalogos.test'])->get('/');

        $response->assertOk()->assertSee('Produto Aurora')->assertDontSee('Produto Sigiloso');
    }

    public function test_admin_cannot_update_product_from_another_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente A', 'slug' => 'cliente-a']);
        $other = Tenant::create(['name' => 'Cliente B', 'slug' => 'cliente-b']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::create(['tenant_id' => $other->id, 'name' => 'Produto B', 'slug' => 'produto-b']);

        $this->actingAs($user)->put(route('admin.products.update', $product), [
            'name' => 'Produto alterado', 'status' => 'published',
        ])->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Produto B']);
    }

    public function test_product_category_must_belong_to_authenticated_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente A', 'slug' => 'cliente-a']);
        $other = Tenant::create(['name' => 'Cliente B', 'slug' => 'cliente-b']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $foreignCategory = Category::create(['tenant_id' => $other->id, 'name' => 'Privada', 'slug' => 'privada']);

        $this->actingAs($user)->post(route('admin.products.store'), [
            'name' => 'Novo produto', 'status' => 'published', 'category_id' => $foreignCategory->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('products', ['tenant_id' => $tenant->id, 'name' => 'Novo produto']);
    }

    public function test_theme_update_changes_only_current_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente A', 'slug' => 'cliente-a']);
        $other = Tenant::create(['name' => 'Cliente B', 'slug' => 'cliente-b', 'theme' => ['primary' => '#000000']]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)->put(route('admin.theme.update'), [
            'primary' => '#173f35', 'accent' => '#e48a4a', 'surface' => '#f4f6f3',
            'hero_title' => 'Catálogo do Cliente A', 'hero_text' => 'Uma coleção exclusiva.',
            'font_style' => 'modern', 'card_style' => 'soft',
        ])->assertRedirect();

        $this->assertSame('#173f35', $tenant->fresh()->theme['primary']);
        $this->assertSame('#000000', $other->fresh()->theme['primary']);
    }
}
