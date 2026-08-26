<?php

namespace App\Models;

use App\Enums\OutboundEmailStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\OutboundEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutboundEmail extends Model
{
    /** @use HasFactory<OutboundEmailFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['token', 'user_id', 'primary_contact_id', 'armory_email_template_id', 'email_signature_id', 'related_type', 'related_id', 'status', 'from_address', 'from_name', 'to_json', 'cc_json', 'bcc_json', 'subject', 'body_text', 'final_text', 'final_html', 'signature_text', 'signature_html', 'attempt_count', 'failure_message', 'queued_at', 'sending_at', 'sent_at', 'failed_at', 'cancelled_at', 'last_attempt_at'];
    protected function casts(): array { return ['status' => OutboundEmailStatus::class, 'to_json' => 'array', 'cc_json' => 'array', 'bcc_json' => 'array', 'queued_at' => 'datetime', 'sending_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime', 'last_attempt_at' => 'datetime']; }
    public function getRouteKeyName(): string { return 'token'; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function primaryContact(): BelongsTo { return $this->belongsTo(Contact::class, 'primary_contact_id'); }
    public function template(): BelongsTo { return $this->belongsTo(ArmoryEmailTemplate::class, 'armory_email_template_id'); }
    public function signature(): BelongsTo { return $this->belongsTo(EmailSignature::class, 'email_signature_id'); }
    public function related(): MorphTo { return $this->morphTo(); }
    public function attachments(): HasMany { return $this->hasMany(OutboundEmailAttachment::class); }
    public function auditExcludedAttributes(): array { return ['body_text', 'final_text', 'final_html', 'signature_text', 'signature_html']; }
}
