<x-layouts.app title="Edit {{ $case->case_number }}" heading="Edit {{ $case->case_number }}" subheading="PreTax Auction acquisition">
    <form method="POST" action="{{ route('pre-auction.update',$case) }}" class="mx-auto max-w-6xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@include('pre-auction._form')</form>
</x-layouts.app>
