<?php

namespace App\Http\Controllers;

use App\Enums\PreAuctionContactRole;
use App\Models\PreAuctionAcquisition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PreAuctionAcquisitionContactController extends Controller
{
    public function store(Request $request, PreAuctionAcquisition $preAuction): RedirectResponse
    {
        Gate::authorize('update', $preAuction);
        $data = $request->validate([
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'role' => ['required', Rule::enum(PreAuctionContactRole::class)],
            'relationship_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ((int) $data['contact_id'] === (int) $preAuction->owner_contact_id) $data['role'] = PreAuctionContactRole::Owner->value;
        $preAuction->contacts()->syncWithoutDetaching([
            $data['contact_id'] => ['role' => $data['role'], 'relationship_notes' => $data['relationship_notes'] ?? null, 'created_by' => $request->user()->id],
        ]);

        return back()->with('success', 'Contact linked to the pre-auction acquisition.');
    }

    public function destroy(PreAuctionAcquisition $preAuction, int $association): RedirectResponse
    {
        Gate::authorize('update', $preAuction);
        $pivot = DB::table('contact_pre_auction_acquisition')->where('id', $association)
            ->where('pre_auction_acquisition_id', $preAuction->id)->first();
        abort_unless($pivot, 404);
        abort_if((int) $pivot->contact_id === (int) $preAuction->owner_contact_id, 422, 'The primary owner cannot be removed. Select a different primary owner first.');
        DB::table('contact_pre_auction_acquisition')->where('id', $association)->delete();

        return back()->with('success', 'Contact removed from the pre-auction acquisition.');
    }
}
