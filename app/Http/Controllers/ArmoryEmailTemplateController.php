<?php

namespace App\Http\Controllers;

use App\Enums\ArmoryEmailTemplateCategory;
use App\Enums\ArmoryEmailTemplateStatus;
use App\Http\Requests\StoreArmoryEmailTemplateRequest;
use App\Http\Requests\UpdateArmoryEmailTemplateRequest;
use App\Models\ArmoryEmailTemplate;
use App\Services\ArmoryEmailTemplateService;
use App\Services\SafeEmailHtmlRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArmoryEmailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ArmoryEmailTemplate::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(ArmoryEmailTemplateCategory::class)],
            'status' => ['nullable', Rule::enum(ArmoryEmailTemplateStatus::class)],
            'sort' => ['nullable', Rule::in(['template', 'category', 'subject', 'version', 'status', 'updated'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $validated['sort'] ?? 'updated';
        $direction = $validated['direction'] ?? 'desc';

        $templates = ArmoryEmailTemplate::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('body_text', 'like', "%{$search}%");
            }))
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($sort === 'template', fn ($query) => $query->orderBy('name', $direction))
            ->when($sort === 'category', fn ($query) => $query->orderByRaw(
                "case category when 'seller_outreach' then 1 when 'buyer_outreach' then 2 when 'surplus_outreach' then 3 when 'pre_tax_auction_outreach' then 4 when 'follow_up' then 5 when 'offers_and_contracts' then 6 when 'closing' then 7 when 'internal' then 8 when 'other' then 9 else 10 end {$direction}"
            ))
            ->when($sort === 'subject', fn ($query) => $query->orderBy('subject', $direction))
            ->when($sort === 'version', fn ($query) => $query->orderBy('version_label', $direction))
            ->when($sort === 'status', fn ($query) => $query->orderByRaw(
                "case status when 'active' then 1 when 'draft' then 2 when 'retired' then 3 else 4 end {$direction}"
            ))
            ->when($sort === 'updated', fn ($query) => $query->orderBy('updated_at', $direction))
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();

        return view('armory.email-templates.index', ['templates' => $templates, ...$this->formData()]);
    }

    public function create(): View
    {
        Gate::authorize('create', ArmoryEmailTemplate::class);

        return view('armory.email-templates.create', $this->formData());
    }

    public function store(StoreArmoryEmailTemplateRequest $request, ArmoryEmailTemplateService $service): RedirectResponse
    {
        $template = $service->create($request->validated(), $request->file('attachments', []), $request->user());

        return redirect()->route('armory.email-templates.index')->with(
            'success',
            "Email template “{$template->name}” was saved successfully."
        );
    }

    public function show(ArmoryEmailTemplate $emailTemplate, SafeEmailHtmlRenderer $renderer): View
    {
        Gate::authorize('view', $emailTemplate);
        $emailTemplate->load(['creator:id,name', 'updater:id,name', 'attachments']);

        return view('armory.email-templates.show', ['template' => $emailTemplate, 'previewHtml' => $renderer->render($emailTemplate->body_text)]);
    }

    public function edit(ArmoryEmailTemplate $emailTemplate): View
    {
        Gate::authorize('update', $emailTemplate);

        return view('armory.email-templates.edit', ['template' => $emailTemplate->load('attachments'), ...$this->formData()]);
    }

    public function update(UpdateArmoryEmailTemplateRequest $request, ArmoryEmailTemplate $emailTemplate, ArmoryEmailTemplateService $service): RedirectResponse
    {
        $service->update($emailTemplate, $request->validated(), $request->file('attachments', []), $request->user());

        return redirect()->route('armory.email-templates.show', $emailTemplate)->with('success', 'Email template updated.');
    }

    public function destroy(Request $request, ArmoryEmailTemplate $emailTemplate): RedirectResponse
    {
        Gate::authorize('delete', $emailTemplate);
        $emailTemplate->updateQuietly(['updated_by' => $request->user()->id]);
        $emailTemplate->delete();

        return redirect()->route('armory.email-templates.index')->with('success', 'Email template archived.');
    }

    private function formData(): array
    {
        return [
            'categories' => ArmoryEmailTemplateCategory::cases(),
            'statuses' => ArmoryEmailTemplateStatus::cases(),
            'mergeTags' => config('email.merge_fields'),
        ];
    }
}
