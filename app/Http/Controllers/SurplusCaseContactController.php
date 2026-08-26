<?php

namespace App\Http\Controllers;

use App\Enums\SurplusContactRole;
use App\Models\AuditLog;
use App\Models\SurplusCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SurplusCaseContactController extends Controller
{
    public function store(Request $request, SurplusCase $surplus): RedirectResponse
    {
        Gate::authorize('update', $surplus);
        $data = $request->validate([
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'role' => ['required', Rule::enum(SurplusContactRole::class)],
            'relationship_notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $surplus, $data): void {
            $existing = DB::table('contact_surplus_case')->where('surplus_case_id', $surplus->id)
                ->where('contact_id', $data['contact_id'])->first();
            DB::table('contact_surplus_case')->updateOrInsert(
                ['surplus_case_id' => $surplus->id, 'contact_id' => $data['contact_id']],
                ['role' => $data['role'], 'relationship_notes' => $data['relationship_notes'] ?? null,
                    'created_by' => $existing?->created_by ?? $request->user()->id,
                    'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()],
            );
            AuditLog::query()->create([
                'user_id' => $request->user()->id, 'event' => $existing ? 'contact_link_updated' : 'contact_linked',
                'auditable_type' => $surplus->getMorphClass(), 'auditable_id' => $surplus->id,
                'old_values' => $existing ? ['contact_id' => $existing->contact_id, 'role' => $existing->role, 'relationship_notes' => $existing->relationship_notes] : null,
                'new_values' => ['contact_id' => $data['contact_id'], 'role' => $data['role'], 'relationship_notes' => $data['relationship_notes'] ?? null],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);
        });

        return back()->with('success', 'Contact linked to this Surplus case.');
    }

    public function destroy(Request $request, SurplusCase $surplus, int $association): RedirectResponse
    {
        Gate::authorize('update', $surplus);
        $link = DB::table('contact_surplus_case')->where('id', $association)
            ->where('surplus_case_id', $surplus->id)->first();
        abort_unless($link, 404);
        if ((int) $link->contact_id === (int) $surplus->claimant_contact_id) {
            return back()->withErrors(['contact' => 'The primary claimant cannot be removed here. Select a different claimant by editing the case first.']);
        }

        DB::transaction(function () use ($request, $surplus, $link): void {
            DB::table('contact_surplus_case')->where('id', $link->id)->delete();
            AuditLog::query()->create([
                'user_id' => $request->user()->id, 'event' => 'contact_unlinked',
                'auditable_type' => $surplus->getMorphClass(), 'auditable_id' => $surplus->id,
                'old_values' => ['contact_id' => $link->contact_id, 'role' => $link->role, 'relationship_notes' => $link->relationship_notes],
                'new_values' => null, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);
        });

        return back()->with('success', 'Contact removed from this Surplus case.');
    }
}
