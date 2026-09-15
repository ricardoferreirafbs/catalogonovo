<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['tenant_id', 'parent_id', 'name', 'slug', 'description', 'sort_order', 'is_active', 'show_in_menu'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_in_menu' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function depth(): int
    {
        $depth = 1;
        $parent = $this->parent;
        while ($parent && $depth < 5) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function breadcrumbName(): string
    {
        $parts = [$this->name];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($parts, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' › ', $parts);
    }
}
