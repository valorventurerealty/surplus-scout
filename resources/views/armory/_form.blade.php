@csrf
@isset($script) @method('PUT') @endisset

@if($errors->any())
    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300">
        <p class="font-semibold">The script was not saved.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2"><label for="title" class="text-sm font-medium">Script title</label><input id="title" name="title" required maxlength="180" value="{{ old('title', $script->title ?? '') }}" class="form-input"><x-form-error name="title" /></div>
    <div><label for="category" class="text-sm font-medium">Category</label><select id="category" name="category" required class="form-input"><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->value }}" @selected(old('category', isset($script) ? $script->category->value : '') === $category->value)>{{ $category->label() }}</option>@endforeach</select><x-form-error name="category" /></div>
    <div><label for="status" class="text-sm font-medium">Status</label><select id="status" name="status" required class="form-input">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', isset($script) ? $script->status->value : 'draft') === $status->value)>{{ $status->label() }}</option>@endforeach</select><x-form-error name="status" /></div>
    <div><label for="version_label" class="text-sm font-medium">Version</label><input id="version_label" name="version_label" required maxlength="40" value="{{ old('version_label', $script->version_label ?? '1.0') }}" class="form-input"><x-form-error name="version_label" /></div>
    <div class="sm:col-span-2"><label for="description" class="text-sm font-medium">Description</label><textarea id="description" name="description" rows="3" maxlength="5000" class="form-input">{{ old('description', $script->description ?? '') }}</textarea><x-form-error name="description" /></div>
    <div class="sm:col-span-2"><label for="content_text" class="text-sm font-medium">Script text</label><textarea id="content_text" name="content_text" rows="14" maxlength="500000" placeholder="Paste the script here to make it readable and searchable inside Armory." class="form-input font-mono text-sm">{{ old('content_text', $script->content_text ?? '') }}</textarea><x-form-error name="content_text" /><p class="mt-2 text-xs text-slate-500">Stored as plain text and always escaped when displayed. TXT and Markdown uploads populate this automatically when no text is entered.</p></div>
    @unless(isset($script))
        <div class="sm:col-span-2 rounded-xl border border-dashed border-slate-300 p-5 dark:border-slate-700"><label for="script_file" class="text-sm font-semibold">Private script file</label><input id="script_file" name="script_file" type="file" accept=".pdf,.doc,.docx,.txt,.md,.rtf" class="mt-3 block w-full text-sm"><x-form-error name="script_file" /><p class="mt-2 text-xs text-slate-500">Optional · PDF, DOC, DOCX, TXT, Markdown, or RTF · Maximum 10 MB · Stored outside public_html. You may save the script now and build guided steps afterward.</p></div>
    @endunless
</div>

<div class="mt-6 flex justify-end gap-3"><a href="{{ isset($script) ? route('armory.show', $script) : route('armory.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Cancel</a><button type="submit" name="save_script" value="1" class="rounded-lg bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-300">{{ isset($script) ? 'Save changes' : 'Save script' }}</button></div>
