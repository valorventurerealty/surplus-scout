<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Models\SurplusCase;
use App\Http\Requests\UpdatePropertyFinancialsRequest;
use App\Models\Contact;
use App\Models\Property;
use App\Services\FinancialSplitCalculator;
use App\Services\PropertyFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialsController extends Controller
{
    public function index(Request $request, FinancialSplitCalculator $calculator): View
    {
        Gate::authorize('viewFinancialWorkspace');

        $properties = Property::query()
            ->with(['financialSplit.contactOne:id,first_name,last_name,company', 'financialSplit.contactTwo:id,first_name,last_name,company'])
            ->orderByRaw('expected_profit is null')
            ->orderByDesc('expected_profit')
            ->orderBy('address')
            ->paginate(20);

        $calculations = $properties->getCollection()->mapWithKeys(fn (Property $property) => [
            $property->id => [
                'expected' => $calculator->calculate($property->all_in_amount, $property->expected_sales_price),
                'actual' => $calculator->calculate($property->all_in_amount, $property->actual_sales_price),
            ],
        ]);

        $totals = Property::query()
            ->whereIn('status', PropertyStatus::financialActualStatuses())
            ->selectRaw(
                'COALESCE(SUM(actual_sales_price), 0) as actual_sales_price, COALESCE(SUM(actual_profit), 0) as actual_profit'
            )->firstOrFail();
        $portfolioTotals = Property::query()
            ->whereIn('status', PropertyStatus::portfolioValueStatuses())
            ->selectRaw(
                'COALESCE(SUM(expected_sales_price), 0) as expected_sales_price, COALESCE(SUM(all_in_amount), 0) as all_in_amount, COALESCE(SUM(expected_profit), 0) as expected_profit'
            )->firstOrFail();
        $totals->expected_sales_price = $portfolioTotals->expected_sales_price;
        $totals->all_in_amount = $portfolioTotals->all_in_amount;
        $totals->expected_profit = $portfolioTotals->expected_profit;

        $canViewSurplusFinancials = $request->user()->canViewSurplusFinancials();
        $surplusRealizedProfit = $canViewSurplusFinancials ? (float) SurplusCase::query()
            ->whereNotNull('paid_at')->whereNotNull('actual_fee')->sum('actual_fee') : 0.0;
        $surplusRecoveredForClaimants = $canViewSurplusFinancials ? (float) SurplusCase::query()
            ->whereNotNull('paid_at')->whereNotNull('recovered_amount')->sum('recovered_amount') : 0.0;
        $surplusNeedsReconciliation = $canViewSurplusFinancials ? SurplusCase::query()
            ->whereNotNull('paid_at')->whereNull('actual_fee')->count() : 0;
        $surplusReceipts = $canViewSurplusFinancials ? SurplusCase::query()
            ->with('claimantContact:id,first_name,last_name')->whereNotNull('paid_at')
            ->latest('paid_at')->latest('id')->limit(20)->get() : collect();
        $combinedActualProfit = (float) $totals->actual_profit + $surplusRealizedProfit;

        return view('financials.index', compact(
            'properties', 'calculations', 'totals', 'surplusRealizedProfit',
            'surplusRecoveredForClaimants', 'surplusNeedsReconciliation',
            'surplusReceipts', 'combinedActualProfit',
            'canViewSurplusFinancials',
        ));
    }

    public function edit(Property $property): View
    {
        Gate::authorize('viewFinancials', $property);
        $property->load(['financialSplit.contactOne', 'financialSplit.contactTwo']);

        return view('financials.edit', [
            'property' => $property,
            'contacts' => Contact::query()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company', 'type']),
        ]);
    }

    public function update(
        UpdatePropertyFinancialsRequest $request,
        Property $property,
        PropertyFinancialService $service,
    ): RedirectResponse {
        $service->update($property, $request->validated(), $request->user());

        return redirect()->route('financials.properties.edit', $property)
            ->with('success', 'Property financials and payment split updated.');
    }
}
