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

    public function test_tenant_creation_reports_an_existing_domain_without_throwing_an_exception(): void
    {
        Tenant::create([
            'name' => 'Empresa existente',
            'slug' => 'empresa-existente',
            'custom_domain' => 'catalogo.exemplo.com.br',
        ]);

        $this->artisan('tenant:create', [
            'name' => 'Nova empresa',
            'email' => 'admin@nova.test',
            '--slug' => 'nova-empresa',
            '--domain' => 'CATALOGO.EXEMPLO.COM.BR',
        ])
            ->expectsOutputToContain('já está vinculado')
            ->assertFailed();

        $this->assertDatabaseMissing('tenants', ['slug' => 'nova-empresa']);
    }

    public function test_category_hierarchy_is_limited_to_four_levels(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $level1 = Category::create(['tenant_id' => $tenant->id, 'name' => 'Nível 1', 'slug' => 'nivel-1']);
        $level2 = Category::create(['tenant_id' => $tenant->id, 'parent_id' => $level1->id, 'name' => 'Nível 2', 'slug' => 'nivel-2']);
        $level3 = Category::create(['tenant_id' => $tenant->id, 'parent_id' => $level2->id, 'name' => 'Nível 3', 'slug' => 'nivel-3']);
        $level4 = Category::create(['tenant_id' => $tenant->id, 'parent_id' => $level3->id, 'name' => 'Nível 4', 'slug' => 'nivel-4']);

        $this->actingAs($user)->post(route('admin.categories.store'), [
            'name' => 'Nível 5', 'parent_id' => $level4->id, 'is_active' => 1,
        ])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['tenant_id' => $tenant->id, 'name' => 'Nível 5']);
    }

    public function test_tenant_user_can_publish_a_category_as_main_menu(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)->post(route('admin.categories.store'), [
            'name' => 'Coleções', 'is_active' => 1, 'show_in_menu' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['tenant_id' => $tenant->id, 'slug' => 'colecoes', 'show_in_menu' => true]);
    }

    public function test_content_editor_updates_only_the_authenticated_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente A', 'slug' => 'cliente-a']);
        $other = Tenant::create(['name' => 'Cliente B', 'slug' => 'cliente-b', 'content' => ['hero_title' => 'Original B']]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)->put(route('admin.content.update'), [
            'hero_title' => 'Nova capa do Cliente A', 'hero_text' => 'Texto personalizado.', 'show_stats' => 1,
        ])->assertRedirect();

        $this->assertSame('Nova capa do Cliente A', $tenant->fresh()->content['hero_title']);
        $this->assertSame('Original B', $other->fresh()->content['hero_title']);
    }

    public function test_accesso_template_renders_editable_sections(): void
    {
        $tenant = Tenant::create([
            'name' => 'Editorial', 'slug' => 'aurora', 'custom_domain' => 'editorial.catalogos.test', 'theme' => ['template' => 'accesso'],
            'content' => ['hero_title' => 'Título totalmente editável', 'experience_title' => 'Experiência personalizada'],
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'editorial.catalogos.test'])->get('/')
            ->assertOk()->assertSee('template-accesso')->assertSee('Título totalmente editável')->assertSee('Experiência personalizada');
    }

    public function test_catalog_builder_pages_are_available_to_tenant_users(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Category::create(['tenant_id' => $tenant->id, 'name' => 'Principal', 'slug' => 'principal', 'show_in_menu' => true]);

        $this->actingAs($user)->get(route('admin.categories.index'))->assertOk()->assertSee('Até quatro níveis');
        $this->actingAs($user)->get(route('admin.content.edit'))->assertOk()->assertSee('Editor de todas as áreas');
        $this->actingAs($user)->get(route('admin.theme.edit'))->assertOk()->assertSee('Editorial Acesso');
    }
}
