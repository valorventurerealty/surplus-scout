<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSurplusCsvImport extends Model
{
    protected $fillable = [
        'token', 'user_id', 'ai_conversation_id', 'disk', 'path', 'original_name', 'mime_type',
        'size_bytes', 'sha256', 'status', 'default_state', 'default_county', 'row_count',
        'valid_row_count', 'result_json', 'error_message', 'expires_at', 'executed_at',
    ];

    protected function casts(): array
    {
        return ['result_json' => 'array', 'expires_at' => 'datetime', 'executed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(AiConversation::class, 'ai_conversation_id'); }
    public function rows(): HasMany { return $this->hasMany(AiSurplusCsvImportRow::class, 'import_id')->orderBy('row_number'); }
}
