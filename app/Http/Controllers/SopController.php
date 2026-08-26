<?php

namespace App\Http\Controllers;

use App\Enums\SopDepartment;
use App\Enums\SopStatus;
use App\Http\Requests\StoreSopRequest;
use App\Http\Requests\UpdateSopRequest;
use App\Models\Sop;
use App\Models\User;
use App\Services\SopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SopController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Sop::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'], 'department' => ['nullable', Rule::enum(SopDepartment::class)],
            'status' => ['nullable', Rule::enum(SopStatus::class)], 'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'review' => ['nullable', Rule::in(['overdue', 'next_30_days', 'no_date'])],
            'sort' => ['nullable', Rule::in(['title', 'department', 'version', 'owner', 'review_date', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $metrics = [
            'total' => Sop::query()->count(), 'active' => Sop::query()->where('status', SopStatus::Active)->count(),
            'draft' => Sop::query()->where('status', SopStatus::Draft)->count(),
            'review_due' => Sop::query()->where('status', '!=', SopStatus::Retired)->whereDate('review_date', '<=', today())->count(),
        ];
        $sort = $validated['sort'] ?? 'updated_at'; $direction = $validated['direction'] ?? 'desc';
        $sops = Sop::query()->with(['owner:id,name', 'uploader:id,name'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('summary', 'like', "%{$search}%")->orWhere('content_text', 'like', "%{$search}%")))
            ->when($validated['department'] ?? null, fn ($query, $department) => $query->where('department', $department))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['owner_user_id'] ?? null, fn ($query, $id) => $query->where('owner_user_id', $id))
            ->when(($validated['review'] ?? null) === 'overdue', fn ($query) => $query->whereDate('review_date', '<', today())->where('status', '!=', SopStatus::Retired))
            ->when(($validated['review'] ?? null) === 'next_30_days', fn ($query) => $query->whereBetween('review_date', [today(), today()->addDays(30)]))
            ->when(($validated['review'] ?? null) === 'no_date', fn ($query) => $query->whereNull('review_date'));
        match ($sort) {
            'title' => $sops->orderBy('title', $direction), 'department' => $sops->orderBy('department', $direction),
            'version' => $sops->orderBy('version_label', $direction),
            'owner' => $sops->orderBy(User::query()->select('name')->whereColumn('users.id', 'sops.owner_user_id')->limit(1), $direction),
            'review_date' => $sops->orderByRaw('review_date is null')->orderBy('review_date', $direction),
            'status' => $sops->orderByRaw("case status when 'active' then 1 when 'draft' then 2 when 'retired' then 3 else 4 end {$direction}"),
            default => $sops->orderBy('updated_at', $direction),
        };

        return view('sops.index', ['sops' => $sops->orderBy('id', $direction)->paginate(25)->withQueryString(), 'metrics' => $metrics, ...$this->formData()]);
    }

    public function create(): View { Gate::authorize('create', Sop::class); return view('sops.create', $this->formData(includeNextSops: true)); }
    public function store(StoreSopRequest $request, SopService $service): RedirectResponse { $sop = $service->create($request->validated(), $request->file('sop_file'), $request->user()); return redirect()->route('sops.show', $sop)->with('success', 'SOP created.'); }
    public function show(Sop $sop): View { Gate::authorize('view', $sop); $sop->load(['owner:id,name,email', 'uploader:id,name', 'creator:id,name', 'nextSop:id,token,title,department,status,summary']); return view('sops.show', compact('sop')); }
    public function edit(Sop $sop): View { Gate::authorize('update', $sop); return view('sops.edit', ['sop' => $sop, ...$this->formData($sop, true)]); }
    public function update(UpdateSopRequest $request, Sop $sop, SopService $service): RedirectResponse { $service->update($sop, $request->validated(), $request->file('sop_file'), $request->user()); return redirect()->route('sops.show', $sop)->with('success', 'SOP updated.'); }
    public function destroy(Request $request, Sop $sop): RedirectResponse { Gate::authorize('delete', $sop); $sop->updateQuietly(['updated_by' => $request->user()->id]); $sop->delete(); return redirect()->route('sops.index')->with('success', 'SOP archived.'); }

    public function download(Sop $sop): StreamedResponse
    {
        Gate::authorize('view', $sop);
        abort_unless($sop->hasFile() && Storage::disk($sop->disk)->exists($sop->path), 404);
        return Storage::disk($sop->disk)->download($sop->path, $sop->original_name, ['Content-Type' => $sop->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function formData(?Sop $current = null, bool $includeNextSops = false): array
    {
        $data = [
            'departments' => SopDepartment::cases(),
            'statuses' => SopStatus::cases(),
            'owners' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];

        if ($includeNextSops) {
            $data['nextSops'] = Sop::query()
                ->when($current, fn ($query) => $query->whereKeyNot($current->id))
                ->orderBy('department')->orderBy('title')
                ->get(['id', 'token', 'title', 'department', 'status']);
        }

        return $data;
    }
}
