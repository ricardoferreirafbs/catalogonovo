<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorOccurrence extends Model
{
    public const STATUS_LABELS = [
        'new' => 'Nova',
        'investigating' => 'Em investigação',
        'resolved' => 'Resolvida',
        'ignored' => 'Ignorada',
    ];

    protected $fillable = [
        'protocol',
        'error_code',
        'http_status',
        'status',
        'security_related',
        'personal_data_incident',
        'risk_assessment',
        'affected_subjects_estimate',
        'affected_data_categories',
        'containment_measures',
        'incident_confirmed_at',
        'anpd_notified_at',
        'data_subjects_notified_at',
        'tenant_id',
        'actor_user_id',
        'reviewed_by_user_id',
        'route',
        'method',
        'path',
        'exception_class',
        'ip_hash',
        'internal_notes',
        'resolved_at',
        'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'security_related' => 'boolean',
            'personal_data_incident' => 'boolean',
            'incident_confirmed_at' => 'datetime',
            'anpd_notified_at' => 'datetime',
            'data_subjects_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'retention_until' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function isPlatformFailure(): bool
    {
        return in_array($this->http_status, [500, 503], true);
    }
}
