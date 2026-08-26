<?php

namespace App\Models;

use Database\Factories\ContactIntakeFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactIntakeFile extends Model
{
    /** @use HasFactory<ContactIntakeFileFactory> */
    use HasFactory;

    protected $fillable = [
        'token', 'user_id', 'contact_id', 'disk', 'path', 'original_name', 'mime_type',
        'size_bytes', 'sha256', 'status', 'provider', 'model', 'provider_response_id',
        'extraction_json', 'input_tokens', 'output_tokens', 'error_code', 'error_message',
        'expires_at', 'attached_at',
    ];

    protected function casts(): array
    {
        return [
            'extraction_json' => 'array',
            'expires_at' => 'datetime',
            'attached_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
