<?php

namespace App\Http\Controllers;

use App\Domain\Contacts\ContactNormalizer;
use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\WebsiteChatConversation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class WebsiteChatWebhookController extends Controller
{
    public function __invoke(Request $request, ContactNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:valorventure.us'],
            'event' => ['required', 'in:website_chat'],
            'submitted_at' => ['required', 'date'],
            'session_id' => ['required', 'string', 'max:64'],
            'topic' => ['required', Rule::in(['seller', 'tax_auction', 'surplus', 'other'])],
            'visitor.full_name' => ['required', 'string', 'max:160'],
            'visitor.email' => ['required', 'email:rfc', 'max:200'],
            'visitor.phone' => ['required', 'string', 'max:40'],
            'property.address' => ['nullable', 'string', 'max:300'],
            'property.parcel_id' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:2000'],
            'page_url' => ['nullable', 'url:http,https', 'max:500'],
            'consent_at' => ['required', 'date'],
        ]);

        $existing = WebsiteChatConversation::query()->where('session_id', $validated['session_id'])->first();
        if ($existing) {
            return response()->json(['ok' => true, 'conversation' => $existing->token]);
        }

        $conversation = DB::transaction(function () use ($validated, $normalizer): WebsiteChatConversation {
            $email = $normalizer->email($validated['visitor']['email']);
            $phone = $normalizer->phone($validated['visitor']['phone']);
            $contact = Contact::query()
                ->where(fn ($query) => $query->where('normalized_email', $email)
                    ->when($phone, fn ($query) => $query->orWhere('normalized_phone', $phone)))
                ->first();

            $parts = preg_split('/\s+/', trim($validated['visitor']['full_name']), 2) ?: [];
            $firstName = $parts[0] ?? 'Website';
            $lastName = $parts[1] ?? 'Visitor';
            $contactType = match ($validated['topic']) {
                'surplus' => ContactType::Surplus,
                'tax_auction' => ContactType::PreTaxAuctions,
                'seller' => ContactType::Seller,
                default => ContactType::Other,
            };

            if (! $contact) {
                $contact = Contact::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $validated['visitor']['email'],
                    'phone' => $validated['visitor']['phone'],
                    'type' => $contactType,
                    'status' => ContactStatus::New,
                    'notes' => 'Created from a completed Valorie website chat.',
                ]);
            } else {
                $contact->fill([
                    'email' => $contact->email ?: $validated['visitor']['email'],
                    'phone' => $contact->phone ?: $validated['visitor']['phone'],
                ])->save();
            }

            $token = (string) Str::uuid();
            $property = $validated['property'] ?? [];
            $description = implode("\n", array_filter([
                'Valorie website chat: '.$this->topicLabel($validated['topic']),
                'Property: '.($property['address'] ?? ''),
                'Parcel ID: '.($property['parcel_id'] ?? ''),
                'Visitor message: '.$validated['message'],
                'Conversation: '.$token,
            ], fn (string $line) => ! str_ends_with($line, ': ')));

            $task = $contact->tasks()->create([
                'title' => 'Follow up on Valorie website chat',
                'description' => $description,
                'status' => TaskStatus::Pending,
                'priority' => TaskPriority::High,
                'due_at' => now()->addDay(),
            ]);

            return WebsiteChatConversation::query()->create([
                'token' => $token,
                'session_id' => $validated['session_id'],
                'contact_id' => $contact->id,
                'task_id' => $task->id,
                'topic' => $validated['topic'],
                'status' => 'open',
                'visitor_name' => $validated['visitor']['full_name'],
                'visitor_email' => $validated['visitor']['email'],
                'visitor_phone' => $validated['visitor']['phone'],
                'property_address' => $property['address'] ?? null,
                'parcel_id' => $property['parcel_id'] ?? null,
                'message' => $validated['message'],
                'transcript' => $validated,
                'page_url' => $validated['page_url'] ?? null,
                'consent_at' => CarbonImmutable::parse($validated['consent_at']),
                'submitted_at' => CarbonImmutable::parse($validated['submitted_at']),
            ]);
        });

        $this->notifyTeam($conversation);

        return response()->json(['ok' => true, 'conversation' => $conversation->token], 201);
    }

    private function notifyTeam(WebsiteChatConversation $conversation): void
    {
        try {
            $recipient = (string) config('services.website_chat.notification_email');
            if ($recipient === '') return;
            $body = implode("\n", array_filter([
                'A visitor completed a Valorie chat.',
                '',
                'Name: '.$conversation->visitor_name,
                'Email: '.$conversation->visitor_email,
                'Phone: '.$conversation->visitor_phone,
                'Topic: '.$conversation->topic_label,
                'Property: '.$conversation->property_address,
                'Parcel ID: '.$conversation->parcel_id,
                '',
                $conversation->message,
                '',
                'Open in Command Center: '.url('/website-chats/'.$conversation->token),
            ], fn (?string $line) => $line !== null));
            Mail::raw($body, fn ($mail) => $mail
                ->to($recipient)
                ->replyTo($conversation->visitor_email, $conversation->visitor_name)
                ->subject('New Valorie chat: '.$conversation->topic_label));
        } catch (Throwable $exception) {
            Log::warning('Valorie chat notification email failed.', ['conversation_id' => $conversation->id, 'error' => $exception->getMessage()]);
        }
    }

    private function topicLabel(string $topic): string
    {
        return match ($topic) {
            'seller' => 'Sell a property',
            'tax_auction' => 'Property facing tax auction',
            'surplus' => 'Surplus funds',
            default => 'Something else',
        };
    }
}
