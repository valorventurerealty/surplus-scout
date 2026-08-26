<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Task;
use App\Models\WebsiteChatConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebsiteChatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'website-chat-test-secret-that-is-longer-than-thirty-two-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.website_chat.webhook_secret', self::SECRET);
        config()->set('services.website_chat.notification_email', 'ValorVentureRealty@gmail.com');
        Mail::fake();
    }

    public function test_webhook_rejects_invalid_credentials(): void
    {
        $this->postJson(route('integrations.website-chat'), $this->payload(), [
            'X-VVR-Website-Chat-Secret' => 'wrong-secret',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('website_chat_conversations', 0);
    }

    public function test_completed_chat_creates_contact_task_and_conversation(): void
    {
        $this->postJson(route('integrations.website-chat'), $this->payload(), $this->headers())
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $contact = Contact::query()->sole();
        $task = Task::query()->sole();
        $conversation = WebsiteChatConversation::query()->sole();

        $this->assertSame('Jamie', $contact->first_name);
        $this->assertSame('Owner', $contact->last_name);
        $this->assertSame('seller', $contact->type->value);
        $this->assertTrue($task->taskable->is($contact));
        $this->assertSame('high', $task->priority->value);
        $this->assertTrue($conversation->contact->is($contact));
        $this->assertTrue($conversation->task->is($task));
    }

    public function test_duplicate_session_is_idempotent(): void
    {
        $this->postJson(route('integrations.website-chat'), $this->payload(), $this->headers())->assertCreated();
        $this->postJson(route('integrations.website-chat'), $this->payload(), $this->headers())->assertOk();

        $this->assertDatabaseCount('website_chat_conversations', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('tasks', 1);
    }

    private function headers(): array
    {
        return ['X-VVR-Website-Chat-Secret' => self::SECRET];
    }

    private function payload(): array
    {
        return [
            'source' => 'valorventure.us',
            'event' => 'website_chat',
            'submitted_at' => now()->toIso8601String(),
            'session_id' => 'website-session-123',
            'topic' => 'seller',
            'visitor' => [
                'full_name' => 'Jamie Owner',
                'email' => 'jamie@example.com',
                'phone' => '(407) 555-0199',
            ],
            'property' => [
                'address' => '123 Palm Ave, Orlando, FL',
                'parcel_id' => 'PARCEL-123',
            ],
            'message' => 'I would like to discuss selling this land.',
            'page_url' => 'https://valorventure.us/',
            'consent_at' => now()->toIso8601String(),
        ];
    }
}
