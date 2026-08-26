<?php

namespace App\Services;

use App\Contracts\AgentOrchestratorInterface;
use App\Contracts\ApprovalServiceInterface;
use App\Contracts\ToolExecutorInterface;
use App\Models\AiActionPlan;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VvrAiActionService implements AgentOrchestratorInterface, ApprovalServiceInterface
{
    public function __construct(
        private readonly VvrAiActionPlanner $planner,
        private readonly ToolExecutorInterface $executor,
    ) {}

    public function prepare(AiConversation $conversation, string $prompt, User $user): array
    {
        $proposal = $this->planner->plan($conversation, $prompt, $user);
        if ($proposal['intent'] === 'create_property_from_documents') {
            return ['property_intake' => true, 'proposal' => $proposal];
        }

        $plan = DB::transaction(function () use ($conversation, $proposal, $user): AiActionPlan {
            $calls = $proposal['tool_calls'];
            $requiresApproval = collect($calls)->contains(fn (array $call): bool => $call['requires_approval']);
            $status = $calls === [] ? 'needs_input' : ($requiresApproval ? 'pending_approval' : 'approved');
            $plan = AiActionPlan::query()->create([
                'token' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'intent' => $proposal['intent'],
                'summary' => $proposal['summary'],
                'risk_level' => collect($calls)->max('risk_level') ?? 0,
                'status' => $status,
                'missing_information_json' => $proposal['missing_information'],
                'warnings_json' => $proposal['warnings'],
                'expires_at' => $requiresApproval ? now()->addMinutes((int) config('ai.approval_expiration_minutes', 60)) : null,
            ]);
            foreach ($calls as $call) {
                $plan->toolCalls()->create([
                    'sequence' => $call['sequence'],
                    'tool_name' => $call['name'],
                    'action_summary' => $call['summary'],
                    'risk_level' => $call['risk_level'],
                    'requires_approval' => $call['requires_approval'],
                    'arguments_json' => $call['arguments'],
                    'status' => 'proposed',
                    'idempotency_key' => hash('sha256', $conversation->id.'|'.$call['sequence'].'|'.$call['name'].'|'.$this->canonicalJson($call['arguments'])),
                ]);
            }
            $conversation->update(['intent' => $proposal['intent'], 'status' => $status, 'last_message_at' => now()]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $proposal['summary'],
                'metadata_json' => ['action_plan_id' => $plan->id, 'tool_count' => count($calls)],
            ]);
            $this->audit($conversation, $plan, $user, 'plan_created', null, ['risk_level' => $plan->risk_level, 'tool_count' => count($calls)]);

            return $plan;
        });

        if ($plan->status === 'approved') {
            $plan = $this->execute($plan, $user, false);
        }

        return ['property_intake' => false, 'plan' => $plan->fresh('toolCalls')];
    }

    public function approve(AiActionPlan $plan, User $user): AiActionPlan
    {
        return $this->execute($plan, $user, true);
    }

    public function reject(AiActionPlan $plan, User $user, ?string $reason): AiActionPlan
    {
        $this->assertOwner($plan, $user);

        return DB::transaction(function () use ($plan, $user, $reason): AiActionPlan {
            $plan = AiActionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if (! in_array($plan->status, ['pending_approval', 'approved'], true)) {
                throw ValidationException::withMessages(['approval' => 'This plan is no longer awaiting a decision.']);
            }
            $plan->update(['status' => 'rejected', 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            $plan->conversation()->update(['status' => 'rejected', 'last_message_at' => now()]);
            $plan->conversation->messages()->create(['role' => 'assistant', 'content' => 'The proposed action plan was rejected. No CRM changes were made.']);
            $this->audit($plan->conversation, $plan, $user, 'plan_rejected', null, ['reason' => $reason]);

            return $plan->refresh();
        });
    }

    private function execute(AiActionPlan $plan, User $user, bool $approvalRequired): AiActionPlan
    {
        $this->assertOwner($plan, $user);
        if ($approvalRequired && $plan->status === 'pending_approval' && $plan->expires_at?->isPast()) {
            $plan->update(['status' => 'expired']);
            $plan->conversation()->update(['status' => 'expired', 'last_message_at' => now()]);
            throw ValidationException::withMessages(['approval' => 'This approval request expired. Ask VVR AI to prepare a new plan.']);
        }
        try {
            return DB::transaction(function () use ($plan, $user, $approvalRequired): AiActionPlan {
                $plan = AiActionPlan::query()->lockForUpdate()->with('toolCalls')->findOrFail($plan->id);
                if ($plan->status === 'completed') {
                    return $plan;
                }
                if ($approvalRequired && $plan->status !== 'pending_approval') {
                    throw ValidationException::withMessages(['approval' => 'This plan is no longer awaiting approval.']);
                }
                $plan->update([
                    'status' => 'executing',
                    'approved_by' => $approvalRequired ? $user->id : $plan->approved_by,
                    'approved_at' => $approvalRequired ? now() : $plan->approved_at,
                ]);
                $results = [];
                foreach ($plan->toolCalls as $call) {
                    if ($call->status === 'completed') {
                        $results[] = $call->result_json;
                        continue;
                    }
                    $call->update(['status' => 'executing', 'started_at' => now()]);
                    $result = $this->executor->execute($call->tool_name, $call->arguments_json, $user);
                    $call->update(['status' => 'completed', 'result_json' => $result, 'completed_at' => now()]);
                    $results[] = $result;
                    $this->audit($plan->conversation, $plan, $user, 'tool_completed', $call->tool_name, ['tool_call_id' => $call->id, 'result_ids' => $this->resultIds($result)]);
                }
                $plan->update(['status' => 'completed', 'result_json' => ['steps' => $results], 'executed_at' => now()]);
                $plan->conversation()->update(['status' => 'completed', 'result_json' => ['action_plan_id' => $plan->id], 'last_message_at' => now()]);
                $links = collect($results)->pluck('url')->filter()->unique()->values()->all();
                $plan->conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => 'Completed successfully: '.count($results).' authorized action'.(count($results) === 1 ? '' : 's').' finished and verified.',
                    'metadata_json' => ['action_plan_id' => $plan->id, 'links' => $links],
                ]);
                $this->audit($plan->conversation, $plan, $user, 'plan_completed', null, ['step_count' => count($results)]);

                return $plan->refresh('toolCalls');
            });
        } catch (Throwable $exception) {
            $plan->update(['status' => 'failed', 'result_json' => ['error' => class_basename($exception), 'message' => Str::limit($exception->getMessage(), 500)]]);
            $plan->conversation()->update(['status' => 'failed', 'last_message_at' => now()]);
            $plan->conversation->messages()->create(['role' => 'assistant', 'content' => 'Execution failed and all related CRM writes were rolled back. Review the error and prepare a corrected plan.']);
            $this->audit($plan->conversation, $plan, $user, 'plan_failed', null, ['error' => class_basename($exception)]);
            throw $exception;
        }
    }

    private function assertOwner(AiActionPlan $plan, User $user): void
    {
        if ($plan->user_id !== $user->id || $plan->conversation?->user_id !== $user->id) {
            throw new AuthorizationException('You cannot access this VVR AI action plan.');
        }
    }

    private function canonicalJson(array $arguments): string
    {
        ksort($arguments);

        return (string) json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function audit(AiConversation $conversation, AiActionPlan $plan, User $user, string $event, ?string $tool, array $metadata): void
    {
        DB::table('ai_audit_logs')->insert([
            'conversation_id' => $conversation->id, 'action_plan_id' => $plan->id, 'user_id' => $user->id,
            'event' => $event, 'tool_name' => $tool, 'metadata_json' => json_encode($metadata),
            'ip_address' => request()?->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function resultIds(array $result): array
    {
        return array_filter($result, fn (mixed $value, string $key): bool => str_ends_with($key, '_id') && is_scalar($value), ARRAY_FILTER_USE_BOTH);
    }
}
