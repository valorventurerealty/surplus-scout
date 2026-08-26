<?php

namespace Tests\Unit;

use App\Services\SalesCopilot\SalesCopilotResponseValidator;
use RuntimeException;
use Tests\TestCase;

class SalesCopilotResponseValidatorTest extends TestCase
{
    public function test_rejects_a_recommendation_with_multiple_questions(): void
    {
        $this->expectException(RuntimeException::class);
        (new SalesCopilotResponseValidator)->validate(['classification'=>'test','classification_confidence'=>.9,'conversation_stage'=>'situation','resistance_level'=>'neutral','recommended_response'=>'Why? What happened?','tone'=>['curious'],'delivery_note'=>'Slow','objective'=>'Clarify','reasoning_summary'=>'Needs context','listen_for'=>[],'likely_next_responses'=>[],'next_stage_if_resolved'=>'situation','requires_human_review'=>false,'requires_legal_review'=>false]);
    }
}
