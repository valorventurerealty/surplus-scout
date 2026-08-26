<?php

namespace App\Models;

use App\Enums\ArmoryScriptCategory;
use App\Enums\ArmoryScriptStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ArmoryScriptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArmoryScript extends Model
{
    /** @use HasFactory<ArmoryScriptFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'token', 'title', 'category', 'status', 'version_label', 'description', 'content_text',
        'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => ArmoryScriptCategory::class,
            'status' => ArmoryScriptStatus::class,
            'size_bytes' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasFile(): bool
    {
        return filled($this->disk) && filled($this->path);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ArmoryScriptStep::class)->orderBy('sequence');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ArmorySession::class);
    }

    public function auditExcludedAttributes(): array
    {
        return ['content_text'];
    }
}
