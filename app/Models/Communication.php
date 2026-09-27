<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Communication extends Model
{
    public const CATEGORY_LABELS = [
        'occurrence' => 'Ocorrência',
        'privacy' => 'Privacidade',
        'billing' => 'Pagamento',
        'maintenance' => 'Manutenção',
        'security' => 'Segurança',
        'general' => 'Comunicado geral',
    ];

    public const PRIORITY_LABELS = [
        'normal' => 'Normal',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    public const STATUS_LABELS = [
        'scheduled' => 'Agendada',
        'published' => 'Publicada',
        'closed' => 'Encerrada',
    ];

    protected $fillable = [
        'protocol', 'tenant_id', 'created_by_user_id', 'subject', 'category',
        'priority', 'status', 'recipient_roles', 'requires_acknowledgement',
        'scheduled_at', 'published_at', 'closed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subject' => 'encrypted',
            'recipient_roles' => 'array',
            'requires_acknowledgement' => 'boolean',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'protocol';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? ucfirst($this->category);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
