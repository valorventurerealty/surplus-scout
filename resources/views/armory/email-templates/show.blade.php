<x-layouts.app title="{{ $template->name }}" heading="{{ $template->name }}" subheading="{{ $template->category->label() }} · Version {{ $template->version_label }}">
    @include('armory._navigation', ['active' => 'email-templates'])

    <div class="mx-auto max-w-5xl space-y-6" x-data="{ subjectCopied: false, bodyCopied: false }">
        <div class="flex flex-wrap justify-end gap-3">@can('update', $template)<a href="{{ route('armory.email-templates.edit', $template) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Edit</a>@endcan @can('delete', $template)<form method="POST" action="{{ route('armory.email-templates.destroy', $template) }}" onsubmit="return confirm('Archive this email template?')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 dark:border-rose-900">Archive</button></form>@endcan</div>

        <section class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">@foreach([['Category',$template->category->label()],['Status',$template->status->label()],['Version',$template->version_label],['Updated by',$template->updater?->name ?? $template->creator?->name ?? 'VVR']] as [$label,$value])<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm font-medium">{{ $value }}</dd></div>@endforeach @if($template->description)<div class="sm:col-span-2 lg:col-span-4"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Internal description</dt><dd class="mt-2 whitespace-pre-line text-sm leading-6">{{ $template->description }}</dd></div>@endif</section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-4"><h2 class="font-semibold">Subject</h2><button type="button" @click="navigator.clipboard.writeText($refs.subject.textContent); subjectCopied = true; setTimeout(() => subjectCopied = false, 1500)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-700"><span x-show="!subjectCopied">Copy subject</span><span x-cloak x-show="subjectCopied">Copied</span></button></div>
            <p x-ref="subject" class="mt-4 text-sm font-medium">{{ $template->subject }}</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-4"><div><h2 class="font-semibold">Email body</h2><p class="mt-1 text-xs text-slate-500">Preview only. No email is sent from this screen.</p></div><button type="button" @click="navigator.clipboard.writeText(@js($template->body_text)); bodyCopied = true; setTimeout(() => bodyCopied = false, 1500)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-700"><span x-show="!bodyCopied">Copy body</span><span x-cloak x-show="bodyCopied">Copied</span></button></div>
            <div x-ref="body" class="prose prose-slate mt-5 max-w-none text-sm leading-7 dark:prose-invert">{!! $previewHtml !!}</div>
        </section>

        @if($template->attachments->isNotEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold">Reusable attachments</h2><p class="mt-1 text-xs text-slate-500">These files are copied into drafts created with this template.</p><div class="mt-4 space-y-2">@foreach($template->attachments as $attachment)<a href="{{ route('armory.email-templates.attachments.download', [$template, $attachment]) }}" class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3 text-sm hover:text-amber-600 dark:bg-slate-800"><span>{{ $attachment->original_name }}</span><span class="text-xs text-slate-500">{{ number_format($attachment->size_bytes / 1024, 1) }} KB</span></a>@endforeach</div></section>
        @endif
    </div>
</x-layouts.app>
