<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'aurora'],
            [
                'name' => 'Aurora Casa', 'status' => 'active', 'plan' => 'professional',
                'contact_phone' => '5511999999999',
                'theme' => [
                    ...Tenant::TEMPLATE_PALETTES['classic'],
                    'template' => 'classic',
                    'hero_title' => 'Peças que dão ritmo à sua casa.',
                    'hero_text' => 'Uma seleção de objetos funcionais, materiais honestos e acabamento cuidadoso.',
                    'font_style' => 'modern', 'card_style' => 'soft',
                ],
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@catalogo.test'],
            ['tenant_id' => $tenant->id, 'name' => 'Administrador', 'password' => Hash::make('catalogo123'), 'role' => 'owner']
        );

        $categories = collect([
            ['name' => 'Mesa & cozinha', 'slug' => 'mesa-cozinha'],
            ['name' => 'Organização', 'slug' => 'organizacao'],
            ['name' => 'Iluminação', 'slug' => 'iluminacao'],
        ])->mapWithKeys(function ($category, $index) use ($tenant) {
            $model = Category::updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $category['slug']],
                ['name' => $category['name'], 'sort_order' => $index + 1, 'is_active' => true]
            );

            return [$category['slug'] => $model];
        });

        $products = [
            ['name' => 'Luminária Arco', 'slug' => 'luminaria-arco', 'sku' => 'LUM-014', 'category' => 'iluminacao', 'price' => 389.90, 'promotional_price' => 329.90, 'featured' => true, 'stock_label' => 'Pronta entrega', 'description' => 'Luminária de mesa com cúpula orientável, acabamento fosco e luz aconchegante.'],
            ['name' => 'Bandeja Horizonte', 'slug' => 'bandeja-horizonte', 'sku' => 'BAN-022', 'category' => 'mesa-cozinha', 'price' => 149.00, 'promotional_price' => null, 'featured' => true, 'stock_label' => 'Últimas unidades', 'description' => 'Bandeja versátil em madeira certificada, com bordas suaves e toque natural.'],
            ['name' => 'Organizador Linha', 'slug' => 'organizador-linha', 'sku' => 'ORG-031', 'category' => 'organizacao', 'price' => 89.90, 'promotional_price' => null, 'featured' => false, 'stock_label' => 'Sob encomenda', 'description' => 'Organizador modular para pequenos objetos, ideal para escritório ou entrada.'],
            ['name' => 'Jarra Serena', 'slug' => 'jarra-serena', 'sku' => 'JAR-008', 'category' => 'mesa-cozinha', 'price' => 119.90, 'promotional_price' => 99.90, 'featured' => false, 'stock_label' => 'Pronta entrega', 'description' => 'Jarra de linhas limpas com capacidade de 1,4 litro e acabamento acetinado.'],
        ];

        foreach ($products as $item) {
            $category = $categories[$item['category']];
            unset($item['category']);
            Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $item['slug']],
                array_merge($item, ['category_id' => $category->id, 'status' => 'published'])
            );
        }
    }
}
