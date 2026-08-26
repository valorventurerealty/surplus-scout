<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function canManageContacts(): bool
    {
        return $this->is_active && $this->role->canManageContacts();
    }

    public function canManageProperties(): bool
    {
        return $this->is_active && $this->role->canManageProperties();
    }

    public function canViewPropertyFinancials(): bool
    {
        return $this->is_active && $this->role->canViewPropertyFinancials();
    }

    public function canManageProjections(): bool
    {
        return $this->is_active && $this->role->canManageProjections();
    }

    public function canViewPropertySourceDocuments(): bool
    {
        return $this->is_active && $this->role->canViewPropertySourceDocuments();
    }

    public function canViewContactSourceDocuments(): bool
    {
        return $this->is_active && $this->role->canViewContactSourceDocuments();
    }

    public function canManageArmory(): bool
    {
        return $this->is_active && $this->role->canManageArmory();
    }

    public function canSendEmail(): bool
    {
        return $this->is_active && $this->role->canSendEmail();
    }

    public function canViewAllOutboundEmails(): bool
    {
        return $this->is_active && $this->role->canViewAllOutboundEmails();
    }

    public function canManageEmailSettings(): bool
    {
        return $this->is_active && $this->role->canManageEmailSettings();
    }

    public function outboundEmails(): HasMany
    {
        return $this->hasMany(OutboundEmail::class);
    }

    public function canManageTasks(): bool
    {
        return $this->is_active && $this->role->canManageTasks();
    }

    public function canManageTaskTemplates(): bool
    {
        return $this->is_active && $this->role->canManageTaskTemplates();
    }

    public function canManageCalendar(): bool
    {
        return $this->is_active && $this->role->canManageCalendar();
    }

    public function canManageIntegrations(): bool
    {
        return $this->is_active && $this->role->isAdministrator();
    }

    public function googleCalendarConnections(): HasMany
    {
        return $this->hasMany(GoogleCalendarConnection::class);
    }

    public function canManageDeals(): bool
    {
        return $this->is_active && $this->role->canManageDeals();
    }

    public function canUseVvrAi(): bool
    {
        return $this->is_active && $this->role->canUseVvrAi();
    }

    public function canViewSurplusCases(): bool
    {
        return $this->is_active && $this->role->canViewSurplusCases();
    }

    public function canManageSurplusCases(): bool
    {
        return $this->is_active && $this->role->canManageSurplusCases();
    }

    public function canManageSops(): bool
    {
        return $this->is_active && $this->role->canManageSops();
    }

    public function canViewSurplusFinancials(): bool
    {
        return $this->is_active && $this->role->canViewSurplusFinancials();
    }

    public function canViewPreAuctionAcquisitions(): bool
    {
        return $this->is_active && $this->role->canViewPreAuctionAcquisitions();
    }

    public function canManagePreAuctionAcquisitions(): bool
    {
        return $this->is_active && $this->role->canManagePreAuctionAcquisitions();
    }

    public function canViewPreAuctionFinancials(): bool
    {
        return $this->is_active && $this->role->canViewPreAuctionFinancials();
    }

    public function assignedSurplusCases(): HasMany
    {
        return $this->hasMany(SurplusCase::class, 'assigned_user_id');
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_user_id');
    }

    public function armorySessions(): HasMany
    {
        return $this->hasMany(ArmorySession::class);
    }

    public function assignedDeals(): HasMany
    {
        return $this->hasMany(Deal::class, 'assigned_user_id');
    }
}
