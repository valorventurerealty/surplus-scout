@csrf
@isset($template) @method('PUT') @endisset

@if($errors->any())
    <div class="mb-6 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200" role="alert">
        <p class="font-semibold">The email template was not saved. Please correct the following:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div x-data="{ linkLabel: '', linkUrl: '', linkError: '', insertLink() { const raw=this.linkUrl.trim(); let parsed; try { parsed=new URL(raw); } catch (error) { this.linkError='Enter a complete URL beginning with https://'; return; } if (!['http:', 'https:'].includes(parsed.protocol)) { this.linkError='Only http:// and https:// links are allowed.'; return; } const field=document.getElementById('body_text'); const label=(this.linkLabel.trim() || raw).replace(/[\[\]]/g, ''); const markup='['+label+']('+parsed.toString()+')'; const start=field.selectionStart; field.value=field.value.slice(0,start)+markup+field.value.slice(field.selectionEnd); field.focus(); field.selectionStart=field.selectionEnd=start+markup.length; this.linkLabel=''; this.linkUrl=''; this.linkError=''; } }" class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2"><label for="name" class="text-sm font-medium">Template name</label><input id="name" name="name" required maxlength="180" value="{{ old('name', $template->name ?? '') }}" class="form-input"><x-form-error name="name" /></div>
    <div><label for="category" class="text-sm font-medium">Category</label><select id="category" name="category" required class="form-input">@foreach($categories as $category)<option value="{{ $category->value }}" @selected(old('category', isset($template) ? $template->category->value : \App\Enums\ArmoryEmailTemplateCategory::Other->value) === $category->value)>{{ $category->label() }}</option>@endforeach</select><x-form-error name="category" /></div>
    <div><label for="status" class="text-sm font-medium">Status</label><select id="status" name="status" required class="form-input">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', isset($template) ? $template->status->value : 'draft') === $status->value)>{{ $status->label() }}</option>@endforeach</select><x-form-error name="status" /></div>
    <div><label for="version_label" class="text-sm font-medium">Version</label><input id="version_label" name="version_label" required maxlength="40" value="{{ old('version_label', $template->version_label ?? '1.0') }}" class="form-input"><x-form-error name="version_label" /></div>
    <div class="sm:col-span-2"><label for="description" class="text-sm font-medium">Internal description</label><textarea id="description" name="description" rows="3" maxlength="5000" class="form-input">{{ old('description', $template->description ?? '') }}</textarea><x-form-error name="description" /></div>
    <div class="sm:col-span-2"><label for="subject" class="text-sm font-medium">Email subject</label><input id="subject" name="subject" required maxlength="255" value="{{ old('subject', $template->subject ?? '') }}" class="form-input"><x-form-error name="subject" /></div>
    <div class="sm:col-span-2"><label for="body_text" class="text-sm font-medium">Email body</label><textarea id="body_text" name="body_text" required rows="18" maxlength="500000" class="form-input font-mono text-sm" placeholder="Write the reusable email here.">{{ old('body_text', $template->body_text ?? '') }}</textarea><x-form-error name="body_text" /><p class="mt-2 text-xs text-slate-500">Stored as plain text and escaped when displayed. Clickable links may use <code>[link text](https://example.com)</code>. Saving a template does not send an email.</p></div>

    <section class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
        <h3 class="text-sm font-semibold">Insert a hyperlink</h3>
        <p class="mt-1 text-xs text-slate-500">Add reusable clickable text at the current cursor position. Only HTTP and HTTPS destinations are allowed.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1.5fr_auto]"><div><label for="template_link_label" class="text-xs font-medium">Link text</label><input id="template_link_label" type="text" x-model="linkLabel" class="form-input" placeholder="Review your options"></div><div><label for="template_link_url" class="text-xs font-medium">Destination URL</label><input id="template_link_url" type="url" x-model="linkUrl" class="form-input" placeholder="https://valorventure.us/"></div><button type="button" @click="insertLink()" class="self-end rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300">Insert link</button></div>
        <p x-show="linkError" x-text="linkError" class="mt-2 text-xs font-semibold text-red-600"></p>
    </section>

    <section class="sm:col-span-2 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <h3 class="text-sm font-semibold">Reusable attachments</h3>
        <p class="mt-1 text-xs text-slate-500">These private files are copied into every new draft that uses this template. Select up to {{ config('email.max_attachments') }} files total, {{ config('email.attachment_max_kb') / 1024 }} MB each.</p>
        <input id="template_attachments" name="attachments[]" type="file" multiple class="form-input mt-3" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png">
        <x-form-error name="attachments" /><x-form-error name="attachments.*" />
        @isset($template)
            @if($template->attachments->isNotEmpty())
                <div class="mt-4 space-y-2"><p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Current template attachments</p>@foreach($template->attachments as $attachment)<label class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-800"><span>{{ $attachment->original_name }} · {{ number_format($attachment->size_bytes / 1024, 1) }} KB</span><span class="flex items-center gap-2"><input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}" class="rounded border-slate-300"> Remove</span></label>@endforeach</div>
            @endif
        @endisset
        <x-form-error name="remove_attachments" /><x-form-error name="remove_attachments.*" />
    </section>

    <section class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50">
        <h3 class="text-sm font-semibold">Available merge fields</h3>
        <p class="mt-1 text-xs text-slate-500">Insert these exactly as shown. The Email workspace replaces them with authorized values from the linked CRM record before approval.</p>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($mergeTags as $tag => $label)<div class="rounded-lg bg-white px-3 py-2 dark:bg-slate-900"><code class="text-xs font-semibold text-amber-700 dark:text-amber-400">{{ $tag }}</code><span class="ml-2 text-xs text-slate-500">{{ $label }}</span></div>@endforeach</div>
    </section>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ isset($template) ? route('armory.email-templates.show', $template) : route('armory.email-templates.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Cancel</a>
    @isset($template)
        <button type="submit" form="email-template-form" name="save_email_template" value="1" class="rounded-lg bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-300">Save changes</button>
    @else
        <button type="submit" form="email-template-form" formmethod="POST" formaction="/armory/email-templates/save" name="save_email_template" value="1" class="rounded-lg bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-300">Save email template</button>
    @endisset
</div>
