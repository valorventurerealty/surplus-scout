<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\PhoneInteraction;
use Illuminate\Foundation\Http\FormRequest;

class LinkPhoneInteractionContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $interaction = $this->route('phoneInteraction');

        return $interaction instanceof PhoneInteraction && $this->user()->can('update', $interaction);
    }

    public function rules(): array
    {
        return ['contact_id' => ['required', 'integer', 'exists:contacts,id']];
    }

    public function selectedContact(): Contact
    {
        return Contact::query()->findOrFail($this->integer('contact_id'));
    }
}
