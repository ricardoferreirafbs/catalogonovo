<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'custom_domain', 'status', 'plan', 'contact_phone', 'logo_path', 'theme'];

    protected function casts(): array
    {
        return ['theme' => 'array'];
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

    public function themeValue(string $key, string $fallback): string
    {
        return (string) data_get($this->theme, $key, $fallback);
    }

    public function catalogUrl(): string
    {
        if (app()->environment('local')) {
            return url('/');
        }

        $host = $this->custom_domain ?: $this->slug.'.'.env('CATALOG_BASE_DOMAIN', parse_url(config('app.url'), PHP_URL_HOST));

        return 'https://'.$host;
    }
}
