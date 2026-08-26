<?php

namespace App\Services\SalesCopilot;

use App\Contracts\AiProviderInterface;
use App\Models\SalesCopilotPlaybook;
use App\Models\SalesCopilotSession;
use App\Models\SalesCopilotTurn;
use Illuminate\Support\Facades\DB;

class SalesCopilotEngine
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly SalesCopilotClassifier $classifier,
        private readonly SalesCopilotPlaybookRetriever $retriever,
        private readonly SalesCopilotPromptBuilder $prompts,
        private readonly SalesCopilotResponseValidator $validator,
    ) {}

    public function coach(SalesCopilotSession $session, string $statement, ?string $salespersonPrevious = null): SalesCopilotTurn
    {
        $classification = $this->classifier->classify($statement);
        $playbook = $this->retriever->retrieve($statement, $classification['type'], $session->current_stage);
        $started = hrtime(true); $provider = null; $usedFallback = true;
        $result = $this->deterministic($classification, $playbook, $statement, $session);

        if (! $classification['dnc'] && ! $classification['legal'] && ($playbook === null || $classification['type'] === 'price_fee_concern') && $this->provider->isConfigured()) {
            try {
                $provider = $this->provider->generateStructured(
                    SalesCopilotResponseValidator::schema(), $this->prompts->systemPrompt(),
                    $this->prompts->userPrompt($session, $statement, $salespersonPrevious, $playbook, $classification),
                );
                $result = $this->validator->validate($provider->data);
                $usedFallback = false;
            } catch (\Throwable) {
                // An approved deterministic recommendation is safer than an unavailable or invalid model response.
            }
        }

        $result = $this->validator->validate($result);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);

        return DB::transaction(function () use ($session, $statement, $salespersonPrevious, $classification, $playbook, $provider, $usedFallback, $result, $latency): SalesCopilotTurn {
            $locked = SalesCopilotSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $sequence = ((int) $locked->turns()->max('sequence')) + 1;
            $turn = $locked->turns()->create([
                'sequence'=>$sequence,'prospect_statement'=>$statement,'salesperson_previous'=>$salespersonPrevious,
                'classification'=>$result['classification'],'classification_confidence'=>$result['classification_confidence'],
                'conversation_stage'=>$result['conversation_stage'],'resistance_level'=>$result['resistance_level'],
                'response'=>$result,'matched_playbook_id'=>$playbook?->id,'provider_response_id'=>$provider?->responseId,
                'input_tokens'=>$provider?->inputTokens,'output_tokens'=>$provider?->outputTokens,'latency_ms'=>$latency,
                'used_fallback'=>$usedFallback,'requires_human_review'=>$result['requires_human_review'],'requires_legal_review'=>$result['requires_legal_review'],
            ]);
            $state = $locked->state ?? [];
            $state['concerns_raised'] = array_values(array_unique([...($state['concerns_raised'] ?? []), $result['classification']]));
            $state['next_objective'] = $result['objective'];
            $state['next_action'] = $classification['dnc'] ? 'do_not_contact' : ($classification['legal'] ? 'legal_review' : 'continue_discovery');
            $locked->update([
                'current_stage'=>$result['conversation_stage'],'resistance_level'=>$result['resistance_level'],
                'state'=>$state,'last_coached_at'=>now(),
                'status'=>$classification['dnc'] ? 'completed' : $locked->status,
                'completed_at'=>$classification['dnc'] ? now() : $locked->completed_at,
            ]);
            return $turn;
        });
    }

    private function deterministic(array $classification, ?SalesCopilotPlaybook $playbook, string $statement, SalesCopilotSession $session): array
    {
        if ($classification['dnc']) return $this->result('do_not_contact',1,'objection_resolution','hostile','Understood. We will honor that request and will not contact you again.',['neutral'],'Keep it brief and stop selling.','Acknowledge and honor the do-not-contact request.','This is an explicit do-not-contact instruction.',['No further persuasion'],[],'close_session',true,false);
        if ($classification['legal']) return $this->result('legal_question',.99,'objection_resolution','guarded','That is probably something I do not want to guess on. Let me get the right answer for you.',['concerned'],'Do not speculate or cite a statute.','Pause the sales conversation and obtain human or legal review.','The prospect asked for a legal conclusion beyond approved coaching knowledge.',['The exact legal question','Attorney or probate involvement'],[],'qualifying',true,true);
        if ($classification['type'] === 'price_fee_concern') return $this->result('price_fee_concern',.96,'objection_resolution','guarded','When you say the 12%... what about the fee is giving you the most pause?',['curious'],'Sound open, not defensive.','Clarify whether the concern is value, affordability, comparison, or trust.','The prospect identified the fee as the current concern, so prior timing objections are no longer primary.',['Value concern','Comparison with DIY','Trust concern','Another company'],['I do not see why it costs that much.','I can do it myself.','Another company charges less.'],'qualifying',false,false);
        if ($playbook) return $this->result($classification['type'],$classification['confidence'],'objection_resolution',$classification['resistance'],$playbook->recommended_response,$playbook->tones,'Slow down and sound genuinely '.($playbook->tones[0] ?? 'curious').'.',$playbook->objective,$playbook->notes ?: 'An approved VVR playbook directly matches this concern.',$playbook->listen_for ?? [],$playbook->branches ?? [],'situation',false,false);
        return $this->result('unknown_needs_clarification',.55,$session->current_stage,$classification['resistance'],'Just so I understand... what do you mean by that?',['confused','curious'],'Use a relaxed, genuinely curious tone.','Get enough clarity to select the right conversation path.','There is not enough context to safely assume what the prospect means.',['The specific concern','Whether this is a question, stall, or objection'],[],$session->current_stage,true,false);
    }

    private function result(string $type,float $confidence,string $stage,string $resistance,string $response,array $tone,string $delivery,string $objective,string $why,array $listen,array $next,string $nextStage,bool $human,bool $legal): array
    { return ['classification'=>$type,'classification_confidence'=>$confidence,'conversation_stage'=>$stage,'resistance_level'=>$resistance,'recommended_response'=>$response,'tone'=>$tone,'delivery_note'=>$delivery,'objective'=>$objective,'reasoning_summary'=>$why,'listen_for'=>$listen,'likely_next_responses'=>$next,'next_stage_if_resolved'=>$nextStage,'requires_human_review'=>$human,'requires_legal_review'=>$legal]; }
}
