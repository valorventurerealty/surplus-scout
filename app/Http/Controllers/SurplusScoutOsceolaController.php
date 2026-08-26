<?php

namespace App\Http\Controllers;

use App\Enums\SurplusResearchRunStatus;
use App\Enums\SurplusOwnerResearchStatus;
use App\Models\SurplusCase;
use App\Models\SurplusOwnerResearchBatch;
use App\Models\SurplusResearchRun;
use App\Services\SurplusResearch\ResearchRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SurplusScoutOsceolaController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SurplusCase::class);
        $runs = SurplusResearchRun::query()->where('county', 'Osceola')->with('triggeredBy')->latest()->paginate(15);
        $lastSuccessful = SurplusResearchRun::query()->where('county', 'Osceola')
            ->whereIn('status', [SurplusResearchRunStatus::Completed, SurplusResearchRunStatus::CompletedWithWarnings])
            ->latest('completed_at')->first();
        $activeRun = SurplusResearchRun::query()->where('county', 'Osceola')
            ->whereIn('status', [SurplusResearchRunStatus::Queued, SurplusResearchRunStatus::Running])->latest()->first();
        $ownerBase = SurplusCase::query()->where('source_name', 'Osceola County Clerk')->where('county', 'Osceola');
        $ownerCounts = [];
        foreach (SurplusOwnerResearchStatus::cases() as $status) {
            $ownerCounts[$status->value] = (clone $ownerBase)->where('research_status', $status->value)->count();
        }
        $ownerBatches = SurplusOwnerResearchBatch::query()->where('county', 'Osceola')->with('triggeredBy')->latest()->limit(10)->get();
        $activeOwnerBatch = SurplusOwnerResearchBatch::query()->where('county', 'Osceola')->whereIn('status', ['queued', 'running'])->latest()->first();
        $retryable = implode("','", array_map('addslashes', SurplusOwnerResearchStatus::retryableValues()));
        $ownerCases = (clone $ownerBase)->whereNotNull('research_status')->whereNotIn('research_status', SurplusOwnerResearchStatus::activeValues())
            ->orderByRaw("CASE WHEN research_status = 'pending_owner_research' THEN 0 WHEN research_status IN ('{$retryable}') THEN 1 ELSE 2 END")
            ->oldest('id')->paginate(25, ['*'], 'owner_cases_page')->withQueryString();

        return view('surplus-scout.osceola.index', [
            'runs' => $runs, 'lastSuccessful' => $lastSuccessful, 'activeRun' => $activeRun,
            'availableCount' => SurplusCase::query()->where('source_name', 'Osceola County Clerk')->where('surplus_availability', 'available')->count(),
            'pendingCount' => SurplusCase::query()->where('source_name', 'Osceola County Clerk')->where('research_status', 'pending_owner_research')->count(),
            'ownerCounts' => $ownerCounts, 'ownerStatuses' => SurplusOwnerResearchStatus::cases(),
            'ownerBatches' => $ownerBatches, 'activeOwnerBatch' => $activeOwnerBatch, 'ownerCases' => $ownerCases,
        ]);
    }

    public function store(ResearchRunService $service): RedirectResponse
    {
        Gate::authorize('create', SurplusCase::class);
        $run = $service->queue(request()->user());
        return redirect()->route('surplus-scout.osceola.runs.show', $run)->with('success', 'Osceola research was queued. The shared-hosting worker will process it on the next cron cycle.');
    }

    public function show(SurplusResearchRun $researchRun): View
    {
        Gate::authorize('viewAny', SurplusCase::class);
        abort_unless($researchRun->county === 'Osceola', 404);
        return view('surplus-scout.osceola.show', ['run' => $researchRun->load('triggeredBy')]);
    }
}
