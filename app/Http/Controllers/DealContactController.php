<?php

namespace App\Http\Controllers;

use App\Enums\DealContactRole;
use App\Models\Deal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DealContactController extends Controller
{
    public function store(Request $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);
        $data = $request->validate(['contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')], 'role' => ['required', Rule::enum(DealContactRole::class)]]);
        DB::table('contact_deal')->updateOrInsert(
            ['deal_id' => $deal->id, 'contact_id' => $data['contact_id'], 'role' => $data['role']],
            ['created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()],
        );
        return back()->with('success', 'Deal contact added.');
    }

    public function destroy(Request $request, Deal $deal, int $party): RedirectResponse
    {
        Gate::authorize('update', $deal);
        $deleted = $deal->contacts()->newPivotStatement()->where('id', $party)->where('deal_id', $deal->id)->delete();
        abort_unless($deleted === 1, 404);
        return back()->with('success', 'Deal contact removed.');
    }
}
