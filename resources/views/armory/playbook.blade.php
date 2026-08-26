<x-layouts.app title="Build {{ $script->title }}" heading="Interactive Playbook Builder" subheading="{{ $script->title }}">
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-wrap justify-between gap-3"><a href="{{ route('armory.show',$script) }}" class="text-sm font-semibold text-amber-700 dark:text-amber-400">← Back to script</a>@if($script->steps->isNotEmpty())<a href="{{ route('armory.sessions.create',$script) }}" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950">Preview guided session</a>@endif</div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold">Add guided step</h2><p class="mt-1 text-sm text-slate-500">Use approved variables: <code>@{{contact_name}}</code>, <code>@{{property_address}}</code>, <code>@{{user_name}}</code>, and <code>@{{caller_name}}</code>.</p>
            <form method="POST" action="{{ route('armory.playbook.steps.store', $script, false) }}" class="mt-5 grid gap-4 sm:grid-cols-6">@csrf
                <div class="sm:col-span-4"><label class="text-sm font-medium">Step title</label><input name="title" required maxlength="180" class="form-input"></div><div class="sm:col-span-2"><label class="text-sm font-medium">Sequence</label><input name="sequence" type="number" min="1" max="999" required value="{{ ($script->steps->max('sequence') ?? 0) + 10 }}" class="form-input"></div>
                <div class="sm:col-span-6"><label class="text-sm font-medium">Words to say</label><textarea name="prompt_text" required rows="4" maxlength="20000" class="form-input"></textarea></div>
                <div class="sm:col-span-6"><label class="text-sm font-medium">Private guidance</label><textarea name="guidance_text" rows="2" maxlength="10000" class="form-input" placeholder="Coaching note shown to the VVR user, not read aloud."></textarea></div>
                <div class="sm:col-span-6 text-right"><button type="submit" class="rounded-lg bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950">Add step</button></div>
            </form>
        </section>

        @forelse($script->steps as $step)
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between"><h2 class="font-bold"><span class="mr-2 rounded-full bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-800">{{ $step->sequence }}</span>{{ $step->title }}</h2><form method="POST" action="{{ route('armory.playbook.steps.destroy', $step, false) }}" onsubmit="return confirm('Remove this step and all of its response branches?')">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-rose-600">Remove</button></form></div>
                <form method="POST" action="{{ route('armory.playbook.steps.update', $step, false) }}" class="grid gap-3 sm:grid-cols-6">@csrf @method('PUT')
                    <input name="title" value="{{ $step->title }}" required class="form-input mt-0 sm:col-span-4"><input name="sequence" type="number" min="1" max="999" value="{{ $step->sequence }}" required class="form-input mt-0 sm:col-span-2">
                    <textarea name="prompt_text" rows="3" required class="form-input mt-0 sm:col-span-6">{{ $step->prompt_text }}</textarea><textarea name="guidance_text" rows="2" class="form-input mt-0 sm:col-span-6">{{ $step->guidance_text }}</textarea><div class="sm:col-span-6 text-right"><button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-700">Save step</button></div>
                </form>
                <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800"><h3 class="text-sm font-semibold">Response branches</h3><x-form-error name="next_step_id" />
                    <div class="mt-3 space-y-3">@foreach($step->options as $option)<div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50"><form method="POST" action="{{ route('armory.playbook.options.update', $option, false) }}" class="grid gap-3 sm:grid-cols-12">@csrf @method('PUT')
                        <div class="sm:col-span-3"><label class="text-xs font-medium text-slate-500">Caller response</label><input name="label" value="{{ $option->label }}" required class="form-input mt-1"></div>
                        <div class="sm:col-span-4"><label class="text-xs font-medium text-slate-500">Suggested VVR reply</label><input name="response_text" value="{{ $option->response_text }}" class="form-input mt-1"></div>
                        <div class="sm:col-span-3"><label class="text-xs font-medium text-slate-500">Next step in this script</label><select name="next_step_id" class="form-input mt-1"><option value="">Complete the session</option>@foreach($script->steps as $target)<option value="{{ $target->id }}" @selected($option->next_step_id === $target->id)>Go to: {{ $target->title }}</option>@endforeach</select></div>
                        <div class="sm:col-span-2"><label class="text-xs font-medium text-slate-500">Order</label><input name="sequence" type="number" min="1" max="999" value="{{ $option->sequence }}" required class="form-input mt-1"></div>
                        <div class="sm:col-span-10"><label class="text-xs font-medium text-slate-500">Outcome if session ends</label><input name="outcome_code" value="{{ $option->outcome_code }}" class="form-input mt-1" placeholder="qualified, follow_up..."></div>
                        <div class="flex items-end justify-end sm:col-span-2"><button type="submit" class="rounded-lg border border-amber-400 px-3 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400">Save branch</button></div>
                    </form><form method="POST" action="{{ route('armory.playbook.options.destroy', $option, false) }}" class="mt-2 text-right">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-rose-600">Remove</button></form></div>@endforeach</div>
                    <form method="POST" action="{{ route('armory.playbook.options.store', $step, false) }}" class="mt-4 grid gap-3 rounded-xl border border-dashed border-slate-300 p-3 sm:grid-cols-12 dark:border-slate-700">@csrf
                        <div class="sm:col-span-3"><label class="text-xs font-medium text-slate-500">Caller response</label><input name="label" required class="form-input mt-1"></div>
                        <div class="sm:col-span-4"><label class="text-xs font-medium text-slate-500">Suggested VVR reply</label><input name="response_text" class="form-input mt-1"></div>
                        <div class="sm:col-span-3"><label class="text-xs font-medium text-slate-500">Next step in this script</label><select name="next_step_id" class="form-input mt-1"><option value="">Complete the session</option>@foreach($script->steps as $target)<option value="{{ $target->id }}">Go to: {{ $target->title }}</option>@endforeach</select></div>
                        <div class="sm:col-span-2"><label class="text-xs font-medium text-slate-500">Order</label><input name="sequence" type="number" min="1" max="999" required value="{{ ($step->options->max('sequence') ?? 0) + 10 }}" class="form-input mt-1"></div>
                        <div class="sm:col-span-10"><label class="text-xs font-medium text-slate-500">Outcome if session ends</label><input name="outcome_code" class="form-input mt-1" placeholder="qualified, follow_up..."></div>
                        <div class="flex items-end justify-end sm:col-span-2"><button type="submit" class="rounded-lg border border-amber-400 px-3 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400">+ Add response branch</button></div>
                    </form>
                </div>
            </section>
        @empty<div class="rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center text-slate-500 dark:border-slate-700">Add the first guided step above.</div>@endforelse
    </div>
</x-layouts.app>
