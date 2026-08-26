<?php

namespace App\Http\Controllers;

use App\Models\WebsiteChatConversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteChatController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WebsiteChatConversation::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'topic' => ['nullable', Rule::in(['seller', 'tax_auction', 'surplus', 'other'])],
            'status' => ['nullable', Rule::in(['open', 'resolved'])],
        ]);
        $query = WebsiteChatConversation::query()->with('contact:id,first_name,last_name');
        $metrics = [
            'open' => (clone $query)->where('status', 'open')->count(),
            'today' => (clone $query)->whereDate('submitted_at', today())->count(),
            'total' => (clone $query)->count(),
        ];
        $conversations = $query
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('visitor_name', 'like', "%{$search}%")
                ->orWhere('visitor_email', 'like', "%{$search}%")
                ->orWhere('visitor_phone', 'like', "%{$search}%")
                ->orWhere('property_address', 'like', "%{$search}%")
                ->orWhere('parcel_id', 'like', "%{$search}%")))
            ->when($validated['topic'] ?? null, fn (Builder $query, string $topic) => $query->where('topic', $topic))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('submitted_at')->paginate(25)->withQueryString();
        return view('website-chats.index', compact('conversations', 'metrics'));
    }

    public function show(WebsiteChatConversation $websiteChat): View
    {
        Gate::authorize('view', $websiteChat);
        $websiteChat->load(['contact', 'task']);
        return view('website-chats.show', ['conversation' => $websiteChat]);
    }

    public function update(Request $request, WebsiteChatConversation $websiteChat): RedirectResponse
    {
        Gate::authorize('update', $websiteChat);
        $validated = $request->validate(['status' => ['required', Rule::in(['open', 'resolved'])]]);
        $websiteChat->update($validated);
        return back()->with('success', 'Website chat status updated.');
    }
}
