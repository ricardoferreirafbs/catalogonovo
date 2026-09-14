<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'slug', 'sku', 'description', 'price',
        'promotional_price', 'status', 'featured', 'stock_label', 'whatsapp_message',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'promotional_price' => 'decimal:2', 'featured' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function getCurrentPriceAttribute(): ?string
    {
        return $this->promotional_price ?: $this->price;
    }
}
