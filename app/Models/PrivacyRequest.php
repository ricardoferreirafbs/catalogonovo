<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class PrivacyRequest extends Model
{
    use Notifiable;

    public const TYPE_LABELS = [
        'confirmation_access' => 'Confirmação e acesso',
        'correction' => 'Correção de dados',
        'deletion' => 'Exclusão ou anonimização',
        'consent_revocation' => 'Revogação de consentimento',
        'sharing_information' => 'Informações sobre compartilhamento',
        'opposition' => 'Oposição ao tratamento',
        'other' => 'Outro direito ou dúvida',
    ];

    public const STATUS_LABELS = [
        'awaiting_verification' => 'Aguardando confirmação',
        'verified' => 'Identidade confirmada',
        'in_review' => 'Em análise',
        'awaiting_information' => 'Aguardando informações',
        'completed' => 'Concluída',
        'rejected' => 'Não atendida',
    ];

    protected $fillable = [
        'protocol', 'request_type', 'scope', 'tenant_id', 'requester_name',
        'requester_email', 'requester_email_hash', 'company_reference', 'details',
        'status', 'email_verified_at', 'reviewed_by_user_id', 'internal_notes',
        'requester_message', 'completed_at', 'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'requester_name' => 'encrypted',
            'requester_email' => 'encrypted',
            'company_reference' => 'encrypted',
            'details' => 'encrypted',
            'internal_notes' => 'encrypted',
            'requester_message' => 'encrypted',
            'email_verified_at' => 'datetime',
            'completed_at' => 'datetime',
            'retention_until' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function routeNotificationForMail(): string
    {
        return $this->requester_email;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->request_type] ?? ucfirst($this->request_type);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
