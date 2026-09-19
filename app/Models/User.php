<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const TENANT_ROLE_LABELS = [
        'owner' => 'Proprietário',
        'admin' => 'Administrador',
        'editor' => 'Editor',
        'viewer' => 'Visualizador',
    ];

    private const ROLE_PERMISSIONS = [
        'admin' => [
            'products.view', 'products.manage', 'products.delete',
            'structure.manage', 'content.manage', 'appearance.manage',
            'users.view', 'users.invite', 'users.update', 'users.remove',
        ],
        'editor' => [
            'products.view', 'products.manage',
            'structure.manage', 'content.manage',
        ],
        'viewer' => ['products.view'],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'email_verified_at',
        'invitation_accepted_at',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_counter' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin' && $this->tenant_id === null;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return $this->role === 'owner'
            || in_array($permission, self::ROLE_PERMISSIONS[$this->role] ?? [], true);
    }

    public function roleLabel(): string
    {
        return self::TENANT_ROLE_LABELS[$this->role] ?? ucfirst($this->role);
    }

    public function canAssignTenantRole(string $role): bool
    {
        if (! array_key_exists($role, self::TENANT_ROLE_LABELS)) {
            return false;
        }

        return $this->role === 'owner'
            || ($this->role === 'admin' && in_array($role, ['editor', 'viewer'], true));
    }

    public function canManageTenantUser(User $target): bool
    {
        if ($this->tenant_id === null || $this->tenant_id !== $target->tenant_id || $this->is($target)) {
            return false;
        }

        return $this->role === 'owner'
            || ($this->role === 'admin' && in_array($target->role, ['editor', 'viewer'], true));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
