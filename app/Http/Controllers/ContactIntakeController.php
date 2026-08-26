<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtractContactIntakeRequest;
use App\Services\ContactIntakeService;
use Illuminate\Http\RedirectResponse;

class ContactIntakeController extends Controller
{
    public function extract(ExtractContactIntakeRequest $request, ContactIntakeService $service): RedirectResponse
    {
        $intake = $service->extract($request->file('document'), $request->user());
        $review = $service->review($intake, $request->user());

        return redirect()->route('contacts.create')
            ->withInput([...$review['values'], 'intake_token' => $intake->token])
            ->with('contact_extraction', $review['summary'])
            ->with('success', 'Candidate contact information extracted. Review every field before creating the contact.');
    }
}
