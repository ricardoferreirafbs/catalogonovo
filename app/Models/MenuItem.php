<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = ['tenant_id', 'label', 'url', 'sort_order', 'is_active', 'open_new_tab'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'open_new_tab' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
