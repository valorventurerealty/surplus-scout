<?php

namespace App\Models;

use App\Enums\SopDepartment;
use App\Enums\SopStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\SopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sop extends Model
{
    /** @use HasFactory<SopFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'token', 'title', 'department', 'status', 'next_sop_id', 'version_label', 'owner_user_id', 'summary',
        'content_text', 'effective_date', 'review_date', 'drive_url', 'disk', 'path',
        'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'department' => SopDepartment::class,
            'status' => SopStatus::class,
            'next_sop_id' => 'integer',
            'effective_date' => 'date',
            'review_date' => 'date',
            'size_bytes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function nextSop(): BelongsTo { return $this->belongsTo(self::class, 'next_sop_id'); }
    public function previousSops(): HasMany { return $this->hasMany(self::class, 'next_sop_id'); }

    public function hasFile(): bool
    {
        return filled($this->disk) && filled($this->path);
    }

    public function auditExcludedAttributes(): array
    {
        return ['content_text'];
    }
}
