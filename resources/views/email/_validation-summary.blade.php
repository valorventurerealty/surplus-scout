@if($errors->any())
    <section role="alert" class="mb-6 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
        <h2 class="font-semibold">Draft not saved</h2>
        <p class="mt-1">Correct the following information and press Save draft again:</p>
        <ul class="mt-3 list-disc space-y-1 pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </section>
@endif
