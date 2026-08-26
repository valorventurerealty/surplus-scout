<?php

namespace App\Models;

use App\Enums\ArmoryEmailTemplateCategory;
use App\Enums\ArmoryEmailTemplateStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ArmoryEmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArmoryEmailTemplate extends Model
{
    /** @use HasFactory<ArmoryEmailTemplateFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'token', 'name', 'category', 'status', 'version_label', 'description',
        'subject', 'body_text', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => ArmoryEmailTemplateCategory::class,
            'status' => ArmoryEmailTemplateStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ArmoryEmailTemplateAttachment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function auditExcludedAttributes(): array
    {
        return ['body_text'];
    }
}
