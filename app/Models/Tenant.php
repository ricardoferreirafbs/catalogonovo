<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    public const TEMPLATE_PALETTES = [
        'mimo' => ['primary' => '#7C2944', 'accent' => '#D79A9B', 'surface' => '#FFF9F5', 'dark' => '#572235'],
        'accesso' => ['primary' => '#3478D4', 'accent' => '#FFD51F', 'surface' => '#FBFAF6', 'dark' => '#111A35'],
        'classic' => ['primary' => '#173F35', 'accent' => '#E48A4A', 'surface' => '#F4F6F3', 'dark' => '#14231F'],
    ];

    protected $fillable = ['name', 'slug', 'custom_domain', 'status', 'plan', 'contact_phone', 'logo_path', 'hero_image_path', 'hero_image_2_path', 'hero_image_3_path', 'experience_image_path', 'theme', 'content'];

    protected function casts(): array
    {
        return ['theme' => 'array', 'content' => 'array'];
    }

    public static function defaultTheme(): array
    {
        return array_merge(self::TEMPLATE_PALETTES['classic'], [
            'template' => 'classic',
            'hero_title' => 'Conheça nossa coleção.',
            'hero_text' => 'Produtos selecionados e atendimento próximo.',
            'font_style' => 'modern',
            'card_style' => 'soft',
        ]);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function themeValue(string $key, string $fallback): string
    {
        return (string) data_get($this->theme, $key, $fallback);
    }

    public function contentValue(string $key, mixed $fallback = ''): mixed
    {
        return data_get($this->content, $key, $fallback);
    }

    public function catalogUrl(): string
    {
        if (app()->environment('local')) {
            return url('/');
        }

        $host = $this->custom_domain ?: $this->slug.'.'.env('CATALOG_BASE_DOMAIN', parse_url(config('app.url'), PHP_URL_HOST));

        return 'https://'.$host;
    }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'owner');
    }
}
