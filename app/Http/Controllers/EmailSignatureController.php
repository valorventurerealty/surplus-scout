<?php

namespace App\Http\Controllers;

use App\Models\EmailSignature;
use App\Services\SafeEmailHtmlRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmailSignatureController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', EmailSignature::class);
        return view('email.signatures.index', ['signatures' => EmailSignature::query()->orderByDesc('is_default')->get()]);
    }

    public function edit(EmailSignature $signature): View
    {
        Gate::authorize('update', $signature);
        return view('email.signatures.edit', ['signature' => $signature]);
    }

    public function update(Request $request, EmailSignature $signature, SafeEmailHtmlRenderer $renderer): RedirectResponse
    {
        Gate::authorize('update', $signature);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'body_text' => ['required', 'string', 'max:10000'], 'is_default' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean']]);
        $signature->update(['name' => $data['name'], 'body_text' => $data['body_text'], 'body_html' => $renderer->render($data['body_text']), 'is_default' => $request->boolean('is_default'), 'is_active' => $request->boolean('is_active'), 'updated_by' => $request->user()->id]);
        if ($signature->is_default) EmailSignature::query()->where('id', '!=', $signature->id)->update(['is_default' => false]);
        return redirect()->route('email.signatures.index')->with('success', 'Email signature updated.');
    }
}
