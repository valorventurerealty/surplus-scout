<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArmoryEmailTemplateAttachment extends Model
{
    protected $fillable = ['token', 'armory_email_template_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ArmoryEmailTemplate::class, 'armory_email_template_id');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
