<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PropertyTaxRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyTaxRecord extends Model
{
    /** @use HasFactory<PropertyTaxRecordFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'property_id', 'source_intake_id', 'tax_year', 'market_value', 'assessed_value',
        'taxable_value', 'prior_year_final_tax', 'proposed_tax', 'no_budget_change_tax',
        'non_ad_valorem_assessments', 'assessment_classification', 'source_document_type',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tax_year' => 'integer', 'market_value' => 'decimal:2', 'assessed_value' => 'decimal:2',
            'taxable_value' => 'decimal:2', 'prior_year_final_tax' => 'decimal:2',
            'proposed_tax' => 'decimal:2', 'no_budget_change_tax' => 'decimal:2',
            'non_ad_valorem_assessments' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function sourceIntake(): BelongsTo { return $this->belongsTo(SurplusIntakeFile::class, 'source_intake_id'); }
}
