<x-layouts.app title="New PreTax Auction Acquisition" heading="New PreTax Auction Acquisition" subheading="Evaluate and acquire a Florida owner's interest before a scheduled tax deed auction">
    <form method="POST" action="{{ route('pre-auction.store') }}" class="mx-auto max-w-6xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@include('pre-auction._form')</form>
</x-layouts.app>
