<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundEmailAttachment extends Model
{
    protected $fillable = ['token', 'outbound_email_id', 'armory_email_template_attachment_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by'];
    public function email(): BelongsTo { return $this->belongsTo(OutboundEmail::class, 'outbound_email_id'); }
    public function templateAttachment(): BelongsTo { return $this->belongsTo(ArmoryEmailTemplateAttachment::class, 'armory_email_template_attachment_id'); }
    public function getRouteKeyName(): string { return 'token'; }
}
