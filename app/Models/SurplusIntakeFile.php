<?php

namespace App\Models;

use Database\Factories\SurplusIntakeFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurplusIntakeFile extends Model
{
    /** @use HasFactory<SurplusIntakeFileFactory> */
    use HasFactory;

    protected $fillable = [
        'token', 'user_id', 'ai_conversation_id', 'property_id', 'contact_id', 'surplus_case_id',
        'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'request_fingerprint',
        'status', 'user_prompt', 'provider', 'model', 'provider_response_id', 'extraction_json',
        'input_tokens', 'output_tokens', 'error_code', 'error_message', 'expires_at', 'attached_at',
    ];

    protected function casts(): array
    {
        return ['extraction_json' => 'array', 'expires_at' => 'datetime', 'attached_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(AiConversation::class, 'ai_conversation_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function surplusCase(): BelongsTo { return $this->belongsTo(SurplusCase::class); }
}
