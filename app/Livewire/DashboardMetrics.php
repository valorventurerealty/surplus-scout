<?php

namespace App\Livewire;

use App\Enums\ContactStatus;
use App\Enums\PropertyStatus;
use App\Models\Contact;
use App\Models\Property;
use App\Models\Task;
use App\Models\CalendarEvent;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardMetrics extends Component
{
    public function render(): View
    {
        $userId = auth()->id();
        $canViewFinancials = auth()->user()->canViewPropertyFinancials();
        $portfolioTotals = $canViewFinancials
            ? Property::query()->whereIn('status', PropertyStatus::portfolioValueStatuses())->selectRaw(
                'COALESCE(SUM(expected_sales_price), 0) AS value, COALESCE(SUM(all_in_amount), 0) AS all_in, COALESCE(SUM(expected_profit), 0) AS profit'
            )->first()
            : null;

        return view('livewire.dashboard-metrics', [
            'contactCount' => Contact::query()->count(),
            'newContactCount' => Contact::query()->where('status', ContactStatus::New->value)->count(),
            'propertyCount' => Property::query()->count(),
            'canViewFinancials' => $canViewFinancials,
            'portfolioTotals' => $portfolioTotals,
            'myTasksDue' => Task::query()
                ->open()
                ->where('assigned_user_id', $userId)
                ->whereNotNull('due_at')
                ->where('due_at', '<=', now()->endOfDay())
                ->count(),
            'upcomingAuctionCount' => CalendarEvent::query()
                ->whereBetween('starts_at', [now(), now()->addDays(30)])
                ->count(),
            'recentContacts' => Contact::query()->with('assignedUser:id,name')->latest()->limit(6)->get(),
        ]);
    }
}
