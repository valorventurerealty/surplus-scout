<?php

namespace App\Http\Controllers;

use App\Enums\ProjectionCategory;
use App\Enums\ProjectionScenarioStatus;
use App\Http\Requests\StoreProjectionScenarioRequest;
use App\Http\Requests\UpdateProjectionScenarioRequest;
use App\Models\Contact;
use App\Models\ProjectionScenario;
use App\Services\ProjectionCalculator;
use App\Services\ProjectionScenarioService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectionController extends Controller
{
    public function index(Request $request, ProjectionCalculator $calculator): View
    {
        Gate::authorize('viewAny', ProjectionScenario::class);
        $scenarios = ProjectionScenario::query()
            ->with(['contactOne:id,first_name,last_name,company', 'contactTwo:id,first_name,last_name,company'])
            ->orderByDesc('is_default')
            ->orderByRaw("case when status = 'active' then 0 when status = 'draft' then 1 else 2 end")
            ->orderBy('name')
            ->get();
        $requestedToken = (string) $request->string('scenario');
        $scenario = $requestedToken !== ''
            ? $scenarios->firstWhere('token', $requestedToken)
            : ($scenarios->firstWhere('is_default', true) ?? $scenarios->first());
        if ($requestedToken !== '' && ! $scenario) {
            abort(404);
        }
        $summary = null;
        if ($scenario) {
            $scenario->load(['assumptions', 'entries']);
            $summary = $calculator->summarize($scenario);
        }

        return view('projections.index', [
            'scenarios' => $scenarios,
            'scenario' => $scenario,
            'summary' => $summary,
            'categories' => ProjectionCategory::cases(),
            'monthNames' => $this->monthNames(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ProjectionScenario::class);

        return view('projections.create', $this->formData());
    }

    public function store(
        StoreProjectionScenarioRequest $request,
        ProjectionScenarioService $service,
    ): RedirectResponse {
        $scenario = $service->create($request->validated(), $request->user());

        return redirect()->route('projections.edit', $scenario)
            ->with('success', 'Projection scenario created. Add the monthly plan and save it.');
    }

    public function edit(ProjectionScenario $scenario): View
    {
        Gate::authorize('update', $scenario);
        $scenario->load(['assumptions', 'entries']);

        return view('projections.edit', [
            'scenario' => $scenario,
            'categories' => ProjectionCategory::cases(),
            'monthNames' => $this->monthNames(),
            'assumptionValues' => $scenario->assumptions->mapWithKeys(
                fn ($assumption): array => [$assumption->category->value => $assumption->average_net_profit]
            ),
            'entryValues' => $scenario->entries->mapWithKeys(
                fn ($entry): array => ["{$entry->category->value}.{$entry->year}.{$entry->month}" => $entry->projected_units]
            ),
            ...$this->formData(),
        ]);
    }

    public function update(
        UpdateProjectionScenarioRequest $request,
        ProjectionScenario $scenario,
        ProjectionScenarioService $service,
    ): RedirectResponse {
        $service->update($scenario, $request->validated(), $request->user());

        return redirect()->route('projections.index', ['scenario' => $scenario->token])
            ->with('success', 'Projection scenario and all calculated splits updated.');
    }

    public function makeDefault(
        Request $request,
        ProjectionScenario $scenario,
        ProjectionScenarioService $service,
    ): RedirectResponse {
        Gate::authorize('update', $scenario);
        $service->makeDefault($scenario, $request->user());

        return redirect()->route('projections.index', ['scenario' => $scenario->token])
            ->with('success', 'Default projection scenario updated.');
    }

    public function destroy(
        Request $request,
        ProjectionScenario $scenario,
        ProjectionScenarioService $service,
    ): RedirectResponse {
        Gate::authorize('delete', $scenario);
        $service->archive($scenario, $request->user());

        return redirect()->route('projections.index')->with('success', 'Projection scenario archived.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'statuses' => ProjectionScenarioStatus::cases(),
            'contacts' => Contact::query()->orderBy('last_name')->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'company']),
        ];
    }

    /** @return array<int, string> */
    private function monthNames(): array
    {
        return collect(range(1, 12))->mapWithKeys(
            fn (int $month): array => [$month => CarbonImmutable::create(2000, $month, 1)->format('F')]
        )->all();
    }
}
