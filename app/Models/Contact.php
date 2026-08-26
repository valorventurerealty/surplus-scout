<?php

namespace App\Models;

use App\Domain\Contacts\ContactNormalizer;
use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Models\Concerns\Auditable;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'company', 'email', 'normalized_email', 'phone', 'normalized_phone',
        'mailing_address_line_1', 'mailing_address_line_2', 'mailing_city', 'mailing_state_province',
        'mailing_postal_code', 'mailing_country', 'type', 'status',
        'assigned_user_id', 'next_follow_up_at', 'next_follow_up_purpose', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'status' => ContactStatus::class,
            'next_follow_up_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contact $contact): void {
            $normalizer = app(ContactNormalizer::class);
            $contact->normalized_email = $normalizer->email($contact->email);
            $contact->normalized_phone = $normalizer->phone($contact->phone);
        });
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function outboundEmails(): MorphMany
    {
        return $this->morphMany(OutboundEmail::class, 'related');
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)
            ->withPivot(['relationship_type', 'created_by'])
            ->withTimestamps();
    }

    public function ownedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_contact_id');
    }

    public function buyerNegotiationPlans(): HasMany
    {
        return $this->hasMany(NegotiationPlan::class, 'buyer_contact_id');
    }

    public function intakeFiles(): HasMany
    {
        return $this->hasMany(ContactIntakeFile::class);
    }

    public function armorySessions(): HasMany
    {
        return $this->hasMany(ArmorySession::class);
    }

    public function phoneInteractions(): HasMany
    {
        return $this->hasMany(PhoneInteraction::class);
    }

    public function websiteChatConversations(): HasMany
    {
        return $this->hasMany(WebsiteChatConversation::class);
    }

    public function primaryDeals(): HasMany
    {
        return $this->hasMany(Deal::class, 'primary_contact_id');
    }

    public function surplusCases(): HasMany
    {
        return $this->hasMany(SurplusCase::class, 'claimant_contact_id');
    }

    public function associatedSurplusCases(): BelongsToMany
    {
        return $this->belongsToMany(SurplusCase::class)
            ->withPivot(['id', 'role', 'relationship_notes', 'created_by'])
            ->withTimestamps();
    }

    public function preAuctionAcquisitions(): HasMany
    {
        return $this->hasMany(PreAuctionAcquisition::class, 'owner_contact_id');
    }

    public function associatedPreAuctionAcquisitions(): BelongsToMany
    {
        return $this->belongsToMany(PreAuctionAcquisition::class)
            ->withPivot(['id', 'role', 'relationship_notes', 'created_by'])
            ->withTimestamps();
    }

    public function surplusIntakeFiles(): HasMany
    {
        return $this->hasMany(SurplusIntakeFile::class);
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class)->withPivot(['id', 'role', 'created_by'])->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
