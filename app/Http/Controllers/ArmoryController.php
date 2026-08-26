<?php

namespace App\Http\Controllers;

use App\Enums\ArmoryScriptCategory;
use App\Enums\ArmoryScriptStatus;
use App\Http\Requests\StoreArmoryScriptRequest;
use App\Http\Requests\UpdateArmoryScriptRequest;
use App\Models\ArmoryScript;
use App\Models\ArmorySession;
use App\Services\ArmoryScriptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArmoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ArmoryScript::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(ArmoryScriptCategory::class)],
            'status' => ['nullable', Rule::enum(ArmoryScriptStatus::class)],
            'sort' => ['nullable', Rule::in(['script', 'category', 'version', 'status', 'source', 'updated'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $validated['sort'] ?? 'updated';
        $direction = $validated['direction'] ?? 'desc';

        $scripts = ArmoryScript::query()
            ->with(['uploader:id,name', 'creator:id,name'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('content_text', 'like', "%{$search}%");
            }))
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($sort === 'script', fn ($query) => $query->orderBy('title', $direction))
            ->when($sort === 'category', fn ($query) => $query->orderBy('category', $direction))
            ->when($sort === 'version', fn ($query) => $query->orderBy('version_label', $direction))
            ->when($sort === 'source', fn ($query) => $query->orderBy('original_name', $direction))
            ->when($sort === 'updated', fn ($query) => $query->orderBy('updated_at', $direction))
            ->when($sort === 'status', fn ($query) => $query->orderByRaw(
                "CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'retired' THEN 2 ELSE 3 END {$direction}"
            ))
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();

        return view('armory.index', ['scripts' => $scripts, ...$this->formData()]);
    }

    public function create(): View
    {
        Gate::authorize('create', ArmoryScript::class);

        return view('armory.create', $this->formData());
    }

    public function store(StoreArmoryScriptRequest $request, ArmoryScriptService $service): RedirectResponse
    {
        $script = $service->create($request->validated(), $request->file('script_file'), $request->user());

        return redirect()->route('armory.show', $script)->with('success', 'Script added to Armory.');
    }

    public function show(ArmoryScript $script): View
    {
        Gate::authorize('view', $script);
        $script->load(['uploader:id,name', 'creator:id,name'])->loadCount(['steps', 'sessions']);

        return view('armory.show', compact('script'));
    }

    public function edit(ArmoryScript $script): View
    {
        Gate::authorize('update', $script);

        return view('armory.edit', ['script' => $script, ...$this->formData()]);
    }

    public function update(UpdateArmoryScriptRequest $request, ArmoryScript $script, ArmoryScriptService $service): RedirectResponse
    {
        $service->update($script, $request->validated(), $request->user());

        return redirect()->route('armory.show', $script)->with('success', 'Armory script updated.');
    }

    public function destroy(Request $request, ArmoryScript $script): RedirectResponse
    {
        Gate::authorize('delete', $script);
        abort_if(ArmorySession::query()->where('status', 'in_progress')->where('armory_script_id', $script->id)->exists(), 409, 'This script is currently in use by an active guided session.');
        $script->updateQuietly(['updated_by' => $request->user()->id]);
        $script->delete();

        return redirect()->route('armory.index')->with('success', 'Armory script archived.');
    }

    public function download(ArmoryScript $script): StreamedResponse
    {
        Gate::authorize('view', $script);
        abort_unless($script->hasFile() && Storage::disk($script->disk)->exists($script->path), 404);

        return Storage::disk($script->disk)->download($script->path, $script->original_name, [
            'Content-Type' => $script->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function formData(): array
    {
        return [
            'categories' => ArmoryScriptCategory::cases(),
            'statuses' => ArmoryScriptStatus::cases(),
        ];
    }
}
