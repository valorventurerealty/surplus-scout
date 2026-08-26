<?php

namespace App\Services\SalesCopilot;

use RuntimeException;

class SalesCopilotResponseValidator
{
    private const TONES = ['familiar','curious','confused','concerned','challenging','playful','neutral','detached'];

    public function validate(array $data): array
    {
        foreach (['classification','classification_confidence','conversation_stage','resistance_level','recommended_response','tone','delivery_note','objective','reasoning_summary','listen_for','likely_next_responses','next_stage_if_resolved','requires_human_review','requires_legal_review'] as $field) {
            if (! array_key_exists($field, $data)) throw new RuntimeException("invalid_structured_output: Missing {$field}.");
        }
        if (! is_string($data['recommended_response']) || blank($data['recommended_response']) || mb_strlen($data['recommended_response']) > 900) throw new RuntimeException('invalid_structured_output: Invalid recommended response.');
        if (substr_count($data['recommended_response'], '?') > 1) throw new RuntimeException('invalid_structured_output: The recommendation asks more than one question.');
        if (! is_numeric($data['classification_confidence']) || $data['classification_confidence'] < 0 || $data['classification_confidence'] > 1) throw new RuntimeException('invalid_structured_output: Invalid confidence.');
        if (! is_array($data['tone']) || array_diff($data['tone'], self::TONES)) throw new RuntimeException('invalid_structured_output: Unsupported tone.');
        foreach (['listen_for','likely_next_responses'] as $field) if (! is_array($data[$field]) || count($data[$field]) > 6) throw new RuntimeException("invalid_structured_output: Invalid {$field}.");
        $unsafe = ['we guarantee','you are legally entitled','we are the county','we are the clerk','we are a law firm'];
        foreach ($unsafe as $phrase) if (str_contains(strtolower($data['recommended_response']), $phrase)) throw new RuntimeException('unsafe_structured_output: The recommendation contains a prohibited claim.');
        return $data;
    }

    public static function schema(): array
    {
        return ['type'=>'OBJECT','required'=>['classification','classification_confidence','conversation_stage','resistance_level','recommended_response','tone','delivery_note','objective','reasoning_summary','listen_for','likely_next_responses','next_stage_if_resolved','requires_human_review','requires_legal_review'],'properties'=>[
            'classification'=>['type'=>'STRING'],'classification_confidence'=>['type'=>'NUMBER','minimum'=>0,'maximum'=>1],
            'conversation_stage'=>['type'=>'STRING'],'resistance_level'=>['type'=>'STRING'],
            'recommended_response'=>['type'=>'STRING'],'tone'=>['type'=>'ARRAY','items'=>['type'=>'STRING','enum'=>self::TONES]],
            'delivery_note'=>['type'=>'STRING'],'objective'=>['type'=>'STRING'],'reasoning_summary'=>['type'=>'STRING'],
            'listen_for'=>['type'=>'ARRAY','items'=>['type'=>'STRING']],'likely_next_responses'=>['type'=>'ARRAY','items'=>['type'=>'STRING']],
            'next_stage_if_resolved'=>['type'=>'STRING'],'requires_human_review'=>['type'=>'BOOLEAN'],'requires_legal_review'=>['type'=>'BOOLEAN'],
        ]];
    }
}
