<?php

namespace App\Http\Controllers;

use App\Http\Requests\QueueSurplusOwnerResearchRequest;
use App\Models\SurplusCase;
use App\Models\SurplusOwnerResearchBatch;
use App\Services\SurplusResearch\SurplusOwnerResearchBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SurplusScoutOsceolaOwnerResearchController extends Controller
{
    public function store(QueueSurplusOwnerResearchRequest $request, SurplusOwnerResearchBatchService $service): RedirectResponse
    {
        Gate::authorize('create', SurplusCase::class);
        $data = $request->validated();
        $batch = $service->queue($data['mode'], array_map('intval', $data['case_ids'] ?? []), $request->user());

        return redirect()->route('surplus-scout.osceola.owner-research.show', $batch)
            ->with('success', $batch->total_cases.' owner-research job(s) queued for sequential processing.');
    }

    public function researchCase(Request $request, SurplusCase $surplus, SurplusOwnerResearchBatchService $service): RedirectResponse
    {
        Gate::authorize('update', $surplus);
        $batch = $service->queue('selected', [$surplus->id], $request->user());

        return redirect()->route('surplus-scout.osceola.owner-research.show', $batch)
            ->with('success', 'Owner research queued for '.$surplus->case_number.'.');
    }

    public function show(SurplusOwnerResearchBatch $ownerResearchBatch): View
    {
        Gate::authorize('viewAny', SurplusCase::class);
        abort_unless($ownerResearchBatch->county === 'Osceola', 404);
        $ownerResearchBatch->load(['triggeredBy', 'attempts' => fn ($query) => $query
            ->with(['surplusCase:id,token,case_number,parcel_id,research_status', 'events'])->latest('id')]);

        return view('surplus-scout.osceola.owner-research-show', ['batch' => $ownerResearchBatch]);
    }
}
