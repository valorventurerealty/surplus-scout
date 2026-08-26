<?php

namespace App\Http\Controllers;

use App\Models\ArmoryScript;
use App\Models\ArmoryScriptStep;
use App\Models\ArmoryScriptStepOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArmoryPlaybookController extends Controller
{
    public function show(ArmoryScript $script): View
    {
        Gate::authorize('update', $script);
        $script->load(['steps.options.nextStep']);

        return view('armory.playbook', compact('script'));
    }

    public function storeStep(Request $request, ArmoryScript $script): RedirectResponse
    {
        Gate::authorize('update', $script);
        $data = $this->stepData($request, $script);
        DB::transaction(function () use ($script, $data, $request): void {
            $script->steps()->create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $script->update(['updated_by' => $request->user()->id]);
        });
        return redirect()->route('armory.playbook.show', $script)->with('success', 'Interactive step added.');
    }

    public function updateStep(Request $request, ArmoryScriptStep $step): RedirectResponse
    {
        $script = $step->script;
        Gate::authorize('update', $script);
        $step->update([...$this->stepData($request, $script, $step), 'updated_by' => $request->user()->id]);
        return redirect()->route('armory.playbook.show', $script)->with('success', 'Interactive step updated.');
    }

    public function destroyStep(ArmoryScriptStep $step): RedirectResponse
    {
        $script = $step->script;
        Gate::authorize('update', $script);
        abort_if($script->sessions()->where('status', 'in_progress')->where('current_step_id', $step->id)->exists(), 409, 'This step is in use by an active session.');
        $step->delete();
        return redirect()->route('armory.playbook.show', $script)->with('success', 'Interactive step removed.');
    }

    public function storeOption(Request $request, ArmoryScriptStep $step): RedirectResponse
    {
        $script = $step->script;
        Gate::authorize('update', $script);
        $step->options()->create($this->optionData($request, $step));
        return redirect()->route('armory.playbook.show', $script)->with('success', 'Response branch added.');
    }

    public function updateOption(Request $request, ArmoryScriptStepOption $option): RedirectResponse
    {
        $script = $option->step->script;
        Gate::authorize('update', $script);
        $option->update($this->optionData($request, $option->step, $option));
        return redirect()->route('armory.playbook.show', $script)->with('success', 'Response branch updated.');
    }

    public function destroyOption(ArmoryScriptStepOption $option): RedirectResponse
    {
        $script = $option->step->script;
        Gate::authorize('update', $script);
        $option->delete();
        return redirect()->route('armory.playbook.show', $script)->with('success', 'Response branch removed.');
    }

    private function stepData(Request $request, ArmoryScript $script, ?ArmoryScriptStep $step = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'], 'prompt_text' => ['required', 'string', 'max:20000'],
            'guidance_text' => ['nullable', 'string', 'max:10000'], 'sequence' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('armory_script_steps')->where('armory_script_id', $script->id)->ignore($step)],
        ]);
    }

    private function optionData(Request $request, ArmoryScriptStep $step, ?ArmoryScriptStepOption $option = null): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:180'], 'response_text' => ['nullable', 'string', 'max:10000'],
            'next_step_id' => ['nullable', 'integer', Rule::exists('armory_script_steps', 'id')->where('armory_script_id', $step->armory_script_id)],
            'outcome_code' => ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 _-]+$/'],
            'sequence' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('armory_script_step_options')->where('armory_script_step_id', $step->id)->ignore($option)],
        ]);
    }
}
