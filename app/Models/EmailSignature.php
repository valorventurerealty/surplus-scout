<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\EmailSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSignature extends Model
{
    /** @use HasFactory<EmailSignatureFactory> */
    use Auditable, HasFactory;

    protected $fillable = ['token', 'user_id', 'name', 'is_default', 'is_active', 'body_text', 'body_html', 'created_by', 'updated_by'];
    protected function casts(): array { return ['is_default' => 'boolean', 'is_active' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getRouteKeyName(): string { return 'token'; }
    public function auditExcludedAttributes(): array { return ['body_text', 'body_html']; }
}
