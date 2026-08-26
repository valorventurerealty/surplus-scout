<?php

namespace App\Models;

use App\Enums\SurplusResearchRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurplusResearchRun extends Model
{
    protected $fillable = [
        'token', 'county', 'source_name', 'source_url', 'source_report_date',
        'source_file_disk', 'source_file_path', 'source_file_hash', 'source_file_size',
        'status', 'started_at', 'completed_at', 'records_found', 'new_records',
        'existing_records', 'amount_changes', 'removed_records', 'failed_records',
        'warning_count', 'warnings', 'error_message', 'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SurplusResearchRunStatus::class,
            'source_report_date' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
            'warnings' => 'array',
        ];
    }

    public function getRouteKeyName(): string { return 'token'; }
    public function triggeredBy(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by'); }
    public function amountHistories(): HasMany { return $this->hasMany(SurplusAmountHistory::class, 'research_run_id'); }
}
