<?php

namespace App\Http\Requests;

use App\Domain\Contacts\ContactNormalizer;
use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->input('email')) ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
            'next_follow_up_purpose' => filled($this->input('next_follow_up_purpose'))
                ? trim((string) $this->input('next_follow_up_purpose'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mailing_address_line_1' => ['nullable', 'string', 'max:255'],
            'mailing_address_line_2' => ['nullable', 'string', 'max:255'],
            'mailing_city' => ['nullable', 'string', 'max:120'],
            'mailing_state_province' => ['nullable', 'string', 'max:120'],
            'mailing_postal_code' => ['nullable', 'string', 'max:30'],
            'mailing_country' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::enum(ContactType::class)],
            'status' => ['required', Rule::enum(ContactStatus::class)],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'next_follow_up_at' => ['nullable', 'date', 'required_with:next_follow_up_purpose'],
            'next_follow_up_purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'property_assignments_present' => ['sometimes', 'boolean'],
            'property_ids' => ['nullable', 'array', 'max:250'],
            'property_ids.*' => ['integer', 'distinct', Rule::exists('properties', 'id')->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') === ContactType::Surplus->value && ! $this->user()?->canViewSurplusCases()) {
                $validator->errors()->add('type', 'Your role cannot access Surplus contacts.');
            }

            if ($this->input('type') === ContactType::PreTaxAuctions->value && ! $this->user()?->canViewPreAuctionAcquisitions()) {
                $validator->errors()->add('type', 'Your role cannot access PreTax Auctions contacts.');
            }

            $normalizer = app(ContactNormalizer::class);
            $contact = $this->route('contact');

            if ($email = $normalizer->email($this->input('email'))) {
                if (! $contact || $normalizer->email($contact->email) !== $email) {
                    $query = Contact::query()->where('normalized_email', $email);
                    if ($contact) {
                        $query->whereKeyNot($contact->getKey());
                    }
                    if ($query->exists()) {
                        $validator->errors()->add('email', 'A contact with this email address already exists.');
                    }
                }
            }

            if ($phone = $normalizer->phone($this->input('phone'))) {
                if (! $contact || $normalizer->phone($contact->phone) !== $phone) {
                    $query = Contact::query()->where('normalized_phone', $phone);
                    if ($contact) {
                        $query->whereKeyNot($contact->getKey());
                    }
                    if ($query->exists()) {
                        $validator->errors()->add('phone', 'A contact with this phone number already exists.');
                    }
                }
            }
        }];
    }
}
