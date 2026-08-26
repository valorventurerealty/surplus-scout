<x-layouts.app title="VVR Sales Copilot" heading="VVR Sales Copilot" subheading="AI-powered sales coaching and objection-response training inside the Armory">
    @include('armory._navigation', ['active' => 'sales-copilot'])

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-amber-50 p-6 shadow-sm dark:border-indigo-900/70 dark:from-indigo-950/30 dark:via-slate-900 dark:to-amber-950/20">
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                <div class="max-w-3xl">
                    <span class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">Workspace ready</span>
                    <h2 class="mt-4 text-2xl font-bold tracking-tight">Turn live objections into confident VVR responses</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">This will be VVR's private coaching desk. A team member will enter what a seller, claimant, relative, buyer, or investor said, add the call context, and receive a tailored response grounded in approved VVR scripts, negotiation principles, SOPs, and department rules.</p>
                </div>
                <span class="self-start rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Knowledge setup next</span>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,0.65fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 class="text-lg font-semibold">Coaching session</h2><p class="mt-1 text-sm text-slate-500">The interaction area is reserved and will be activated after your approved training material is installed.</p></div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Not processing yet</span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium">Department
                        <select disabled class="form-input mt-2 opacity-70"><option>Select department</option><option>Surplus Recovery</option><option>PreTax Auctions</option><option>Acquisitions</option><option>Dispositions</option></select>
                    </label>
                    <label class="text-sm font-medium">Conversation stage
                        <select disabled class="form-input mt-2 opacity-70"><option>Select stage</option></select>
                    </label>
                </div>
                <label class="mt-4 block text-sm font-medium">What did they say?
                    <textarea disabled rows="5" class="form-input mt-2 opacity-70" placeholder="Example: I need to think about it and talk to my family."></textarea>
                </label>
                <label class="mt-4 block text-sm font-medium">Relevant context
                    <textarea disabled rows="3" class="form-input mt-2 opacity-70" placeholder="Motivation, timeline, previous discussion, property or case context..."></textarea>
                </label>
                <div class="mt-5 flex justify-end"><button disabled class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white opacity-50">Generate coaching response</button></div>

                <div class="mt-6 rounded-xl border border-dashed border-indigo-300 bg-indigo-50/50 p-6 text-center dark:border-indigo-900 dark:bg-indigo-950/20">
                    <p class="font-semibold text-indigo-900 dark:text-indigo-200">Ready for your sales knowledge</p>
                    <p class="mt-2 text-sm leading-6 text-indigo-800/80 dark:text-indigo-300/80">Your next update will establish the approved response framework, objection library, tone, escalation rules, and source hierarchy before any AI-generated coaching is enabled.</p>
                </div>
            </div>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">Planned coaching output</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        <li>• Recommended response in VVR's voice</li>
                        <li>• Why the response fits the objection</li>
                        <li>• Best follow-up question</li>
                        <li>• What to listen for next</li>
                        <li>• Warnings and escalation guidance</li>
                        <li>• Linked supporting script or SOP</li>
                    </ul>
                </section>
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950/20">
                    <h2 class="font-semibold text-amber-900 dark:text-amber-200">Guardrails</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-900/80 dark:text-amber-200/80">
                        <li>• Coaching will use approved VVR material, not invented policy.</li>
                        <li>• Missing context will produce questions, not assumptions.</li>
                        <li>• Legal, financial, and compliance decisions will be clearly flagged.</li>
                        <li>• Access will remain limited by the logged-in user's permissions.</li>
                    </ul>
                </section>
            </aside>
        </section>
    </div>
</x-layouts.app>
