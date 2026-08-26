<?php

namespace App\Services;

use App\Enums\ArmorySessionStatus;
use App\Models\ArmoryScript;
use App\Models\ArmorySession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArmoryInteractiveSessionService
{
    public function start(ArmoryScript $script, array $data, User $user): ArmorySession
    {
        $firstStep = $script->steps()->first();
        throw_if(! $firstStep, ValidationException::withMessages(['script' => 'Add at least one interactive step before starting this script.']));

        return DB::transaction(function () use ($script, $data, $user, $firstStep): ArmorySession {
            $session = ArmorySession::query()->create([
                'token' => (string) Str::uuid(), 'armory_script_id' => $script->id, 'user_id' => $user->id,
                'contact_id' => $data['contact_id'] ?? null, 'property_id' => $data['property_id'] ?? null,
                'caller_name' => $data['caller_name'] ?? null, 'current_step_id' => $firstStep->id,
                'status' => ArmorySessionStatus::InProgress, 'started_at' => now(),
            ]);
            $session->events()->create(['event_type' => 'started', 'armory_script_step_id' => $firstStep->id]);
            return $session;
        });
    }

    public function advance(ArmorySession $session, ?int $optionId, ?string $stepNotes): ArmorySession
    {
        return DB::transaction(function () use ($session, $optionId, $stepNotes): ArmorySession {
            $session = ArmorySession::query()->lockForUpdate()->findOrFail($session->id);
            $this->ensureInProgress($session);
            $step = $session->currentStep()->with('options')->firstOrFail();
            $script = $session->script()->firstOrFail();
            $option = null;

            if ($step->options->isNotEmpty()) {
                $option = $step->options->firstWhere('id', $optionId);
                throw_if(! $option, ValidationException::withMessages(['option_id' => 'Choose one of the responses for the current step.']));
            } elseif ($optionId) {
                throw ValidationException::withMessages(['option_id' => 'That response does not belong to the current step.']);
            }

            $nextStep = null;

            if ($option?->next_step_id) {
                $nextStep = $script->steps()->find($option->next_step_id);
            } elseif ($step->options->isEmpty()) {
                $nextStep = $script->steps()->where('sequence', '>', $step->sequence)->first();
            }

            $session->events()->create([
                'event_type' => 'advanced', 'armory_script_step_id' => $step->id,
                'armory_script_step_option_id' => $option?->id,
                'payload' => array_filter(['notes' => $stepNotes, 'response' => $option?->label]),
            ]);

            if (filled($stepNotes)) {
                $session->notes = trim(implode("\n\n", array_filter([$session->notes, "{$step->title}: {$stepNotes}"])));
            }

            if ($nextStep) {
                $session->current_step_id = $nextStep->id;
            } else {
                $session->status = ArmorySessionStatus::Completed;
                $session->current_step_id = null;
                $session->outcome = $option?->outcome_code ?: 'completed';
                $session->completed_at = now();
            }
            $session->save();
            return $session->refresh();
        });
    }

    public function finish(ArmorySession $session, string $outcome, ?string $notes, bool $abandoned = false): ArmorySession
    {
        return DB::transaction(function () use ($session, $outcome, $notes, $abandoned): ArmorySession {
            $session = ArmorySession::query()->lockForUpdate()->findOrFail($session->id);
            $this->ensureInProgress($session);
            $session->update([
                'status' => $abandoned ? ArmorySessionStatus::Abandoned : ArmorySessionStatus::Completed,
                'current_step_id' => null, 'outcome' => $outcome,
                'notes' => trim(implode("\n\n", array_filter([$session->notes, $notes]))), 'completed_at' => now(),
            ]);
            $session->events()->create(['event_type' => $abandoned ? 'abandoned' : 'completed', 'payload' => ['outcome' => $outcome]]);
            return $session->refresh();
        });
    }

    public function delete(ArmorySession $session): void
    {
        DB::transaction(function () use ($session): void {
            ArmorySession::query()->lockForUpdate()->findOrFail($session->id)->delete();
        });
    }

    private function ensureInProgress(ArmorySession $session): void
    {
        throw_unless($session->status === ArmorySessionStatus::InProgress, ValidationException::withMessages(['session' => 'This session is already closed.']));
    }
}
