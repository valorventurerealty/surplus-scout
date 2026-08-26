<?php

namespace App\Http\Controllers;

use App\Enums\ArmoryScriptStatus;
use App\Models\ArmoryScript;
use App\Models\ArmorySession;
use App\Models\Contact;
use App\Models\Property;
use App\Services\ArmoryInteractiveSessionService;
use App\Services\ArmoryScriptVariableRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArmorySessionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ArmorySession::class);
        $sessions = ArmorySession::query()->with(['script:id,title', 'user:id,name', 'contact:id,first_name,last_name', 'property:id,address'])
            ->when(! $request->user()->canManageArmory(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest('started_at')->paginate(25);
        $availableScripts = ArmoryScript::query()
            ->whereHas('steps')
            ->when(! $request->user()->canManageArmory(), fn ($query) => $query->where('status', ArmoryScriptStatus::Active))
            ->orderBy('title')
            ->get(['id', 'token', 'title', 'category', 'status']);

        return view('armory.sessions.index', compact('sessions', 'availableScripts'));
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'script_id' => ['required', 'integer', Rule::exists('armory_scripts', 'id')->whereNull('deleted_at')],
        ]);
        $script = ArmoryScript::query()->findOrFail($data['script_id']);
        $this->authorizeRunnable($request, $script);

        return redirect()->route('armory.sessions.create', $script);
    }

    public function create(Request $request, ArmoryScript $script): View
    {
        $this->authorizeRunnable($request, $script);

        return view('armory.sessions.create', [
            'script' => $script,
            'contacts' => Contact::query()->orderBy('first_name')->orderBy('last_name')->limit(500)->get(['id', 'first_name', 'last_name', 'company']),
            'properties' => Property::query()->orderBy('address')->limit(500)->get(['id', 'address', 'city', 'state']),
        ]);
    }

    public function store(Request $request, ArmoryScript $script, ArmoryInteractiveSessionService $service): RedirectResponse
    {
        $this->authorizeRunnable($request, $script);
        $data = $request->validate([
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'caller_name' => ['nullable', 'string', 'max:180'],
        ]);
        $session = $service->start($script, $data, $request->user());
        return redirect()->route('armory.sessions.show', $session)->with('success', 'Interactive session started.');
    }

    public function show(ArmorySession $session, ArmoryScriptVariableRenderer $renderer): View
    {
        Gate::authorize('view', $session);
        $session->load(['script.steps', 'user:id,name', 'contact:id,first_name,last_name,company', 'property:id,address,city,state,postal_code', 'currentStep.options.nextStep', 'events.step', 'events.option']);
        return view('armory.sessions.show', ['session' => $session, 'renderer' => $renderer]);
    }

    public function advance(Request $request, ArmorySession $session, ArmoryInteractiveSessionService $service): RedirectResponse
    {
        Gate::authorize('update', $session);
        $data = $request->validate(['option_id' => ['nullable', 'integer'], 'step_notes' => ['nullable', 'string', 'max:5000']]);
        $service->advance($session, isset($data['option_id']) ? (int) $data['option_id'] : null, $data['step_notes'] ?? null);
        return redirect()->route('armory.sessions.show', $session)->with('success', 'Session advanced.');
    }

    public function finish(Request $request, ArmorySession $session, ArmoryInteractiveSessionService $service): RedirectResponse
    {
        Gate::authorize('update', $session);
        $data = $request->validate(['outcome' => ['required', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:10000']]);
        $service->finish($session, $data['outcome'], $data['notes'] ?? null);
        return redirect()->route('armory.sessions.show', $session)->with('success', 'Interactive session completed.');
    }

    public function abandon(Request $request, ArmorySession $session, ArmoryInteractiveSessionService $service): RedirectResponse
    {
        Gate::authorize('update', $session);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
        $service->finish($session, 'abandoned', $data['notes'] ?? null, true);
        return redirect()->route('armory.sessions.show', $session)->with('success', 'Interactive session closed as abandoned.');
    }

    public function destroy(ArmorySession $session, ArmoryInteractiveSessionService $service): RedirectResponse
    {
        Gate::authorize('delete', $session);
        $service->delete($session);

        return redirect()->route('armory.sessions.index')->with('success', 'Guided session deleted.');
    }

    private function authorizeRunnable(Request $request, ArmoryScript $script): void
    {
        Gate::authorize('view', $script);
        abort_unless($script->status === ArmoryScriptStatus::Active || $request->user()->canManageArmory(), 403);
        abort_if($script->steps()->doesntExist(), 422, 'This script has no interactive steps.');
    }
}
