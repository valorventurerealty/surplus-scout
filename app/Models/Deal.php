<?php

namespace App\Models;

use App\Enums\DealStatus;
use App\Enums\DealType;
use App\Models\Concerns\Auditable;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['token', 'deal_number', 'title', 'type', 'status', 'property_id', 'primary_contact_id', 'assigned_user_id', 'source', 'contract_date', 'due_diligence_deadline', 'projected_close_date', 'actual_close_date', 'offer_amount', 'contract_amount', 'earnest_money', 'projected_revenue', 'actual_revenue', 'document_drive_url', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['type' => DealType::class, 'status' => DealStatus::class, 'contract_date' => 'date', 'due_diligence_deadline' => 'date', 'projected_close_date' => 'date', 'actual_close_date' => 'date', 'offer_amount' => 'decimal:2', 'contract_amount' => 'decimal:2', 'earnest_money' => 'decimal:2', 'projected_revenue' => 'decimal:2', 'actual_revenue' => 'decimal:2'];
    }

    public function getRouteKeyName(): string { return 'token'; }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function primaryContact(): BelongsTo { return $this->belongsTo(Contact::class, 'primary_contact_id'); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function contacts(): BelongsToMany { return $this->belongsToMany(Contact::class)->withPivot(['id', 'role', 'created_by'])->withTimestamps(); }
    public function tasks(): MorphMany { return $this->morphMany(Task::class, 'taskable'); }
    public function outboundEmails(): MorphMany { return $this->morphMany(OutboundEmail::class, 'related'); }
    public function scopeOpen(Builder $query): Builder { return $query->whereNotIn('status', [DealStatus::Closed->value, DealStatus::Cancelled->value]); }
}
