<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\PhoneInteractionMatchStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\PhoneInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BesideIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'a-secure-test-secret-that-is-over-thirty-two-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.beside.webhook_secret', self::SECRET);
    }

    public function test_webhook_rejects_an_invalid_secret(): void
    {
        $this->postJson(route('integrations.beside.events'), $this->payload(), [
            'X-VVR-Beside-Secret' => 'wrong-secret',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('phone_interactions', 0);
    }

    public function test_webhook_refuses_to_run_when_server_secret_is_not_configured(): void
    {
        config()->set('services.beside.webhook_secret', null);

        $this->postJson(route('integrations.beside.events'), $this->payload(), [
            'X-VVR-Beside-Secret' => self::SECRET,
        ])->assertStatus(503);
    }

    public function test_call_is_matched_to_exactly_one_existing_contact_and_audited_compactly(): void
    {
        $contact = Contact::factory()->create(['phone' => '(407) 900-6554']);

        $this->postJson(route('integrations.beside.events'), $this->payload(), $this->headers())
            ->assertCreated()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('created', true)
            ->assertJsonPath('match_status', 'matched');

        $interaction = PhoneInteraction::query()->sole();
        $this->assertTrue($interaction->contact->is($contact));
        $this->assertSame('4079006554', $interaction->normalized_phone);
        $this->assertSame('Caller asked about a property.', $interaction->summary);
        $this->assertSame('Full confidential transcript.', $interaction->transcript);

        $audit = AuditLog::query()->where('event', 'beside_received')->sole();
        $this->assertSame($interaction->id, $audit->auditable_id);
        $this->assertArrayNotHasKey('transcript', $audit->new_values);
        $this->assertArrayNotHasKey('provider_payload', $audit->new_values);
    }

    public function test_duplicate_provider_event_is_idempotent(): void
    {
        $this->postJson(route('integrations.beside.events'), $this->payload(), $this->headers())->assertCreated();
        $this->postJson(route('integrations.beside.events'), $this->payload(), $this->headers())
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertDatabaseCount('phone_interactions', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_later_beside_delivery_enriches_the_existing_call_without_creating_a_duplicate(): void
    {
        $initial = [
            ...$this->payload(),
            'summary' => null,
            'transcript' => null,
            'action_items' => null,
        ];

        $this->postJson(route('integrations.beside.events'), $initial, $this->headers())
            ->assertCreated()
            ->assertJsonPath('updated', false);

        $this->postJson(route('integrations.beside.events'), [
            ...$this->payload(),
            'summary' => 'Beside completed the post-call summary.',
            'transcript' => 'Completed transcript from the call.',
            'action_items' => ['Send the requested property information'],
            'recording_url' => 'https://example.test/recordings/call-1001',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('updated', true);

        $this->assertDatabaseCount('phone_interactions', 1);
        $interaction = PhoneInteraction::query()->sole();
        $this->assertSame('Beside completed the post-call summary.', $interaction->summary);
        $this->assertSame('Completed transcript from the call.', $interaction->transcript);
        $this->assertSame(['Send the requested property information'], $interaction->action_items);
        $this->assertSame('https://example.test/recordings/call-1001', $interaction->recording_url);

        $audit = AuditLog::query()->where('event', 'beside_enriched')->sole();
        $this->assertContains('summary', $audit->new_values['updated_fields']);
        $this->assertArrayNotHasKey('summary', $audit->new_values);
        $this->assertArrayNotHasKey('transcript', $audit->new_values);
    }

    public function test_blank_follow_up_values_do_not_erase_existing_call_notes(): void
    {
        $this->postJson(route('integrations.beside.events'), $this->payload(), $this->headers())->assertCreated();

        $this->postJson(route('integrations.beside.events'), [
            ...$this->payload(),
            'summary' => '   ',
            'transcript' => '',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('updated', false);

        $interaction = PhoneInteraction::query()->sole();
        $this->assertSame('Caller asked about a property.', $interaction->summary);
        $this->assertSame('Full confidential transcript.', $interaction->transcript);
    }

    public function test_zapier_call_id_and_notes_aliases_are_normalized(): void
    {
        $payload = $this->payload();
        unset($payload['event_id'], $payload['summary']);
        $payload['call_id'] = 'beside-call-alias-1002';
        $payload['notes'] = 'Notes supplied by the Beside Zap.';

        $this->postJson(route('integrations.beside.events'), $payload, $this->headers())
            ->assertCreated();

        $interaction = PhoneInteraction::query()->sole();
        $this->assertSame('beside-call-alias-1002', $interaction->provider_event_id);
        $this->assertSame('Notes supplied by the Beside Zap.', $interaction->summary);
    }

    public function test_unmatched_call_does_not_create_a_contact(): void
    {
        $this->postJson(route('integrations.beside.events'), $this->payload(), $this->headers())
            ->assertCreated()
            ->assertJsonPath('match_status', 'unmatched');

        $this->assertDatabaseCount('contacts', 0);
        $this->assertNull(PhoneInteraction::query()->sole()->contact_id);
    }

    public function test_duplicate_contact_phone_is_flagged_as_conflicting(): void
    {
        Contact::factory()->count(2)->create(['phone' => '407-900-6554']);

        $this->postJson(route('integrations.beside.events'), $this->payload(), $this->headers())
            ->assertCreated()
            ->assertJsonPath('match_status', 'conflicting');

        $this->assertNull(PhoneInteraction::query()->sole()->contact_id);
    }

    public function test_invalid_event_type_is_rejected_without_a_write(): void
    {
        $this->postJson(route('integrations.beside.events'), [
            ...$this->payload(),
            'event_type' => 'delete_contact',
        ], $this->headers())->assertUnprocessable();

        $this->assertDatabaseCount('phone_interactions', 0);
    }

    public function test_authenticated_owner_can_view_phone_workspace_and_detail(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $interaction = PhoneInteraction::factory()->create(['summary' => 'Owner-visible summary']);

        $this->actingAs($owner)->get(route('phone-interactions.index'))
            ->assertOk()
            ->assertSee('Owner-visible summary');
        $this->actingAs($owner)->get(route('phone-interactions.show', $interaction))
            ->assertOk()
            ->assertSee('Owner-visible summary');
    }

    public function test_authorized_user_can_review_and_link_an_unmatched_call(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create();
        $interaction = PhoneInteraction::factory()->create();

        $this->actingAs($owner)->patch(route('phone-interactions.contact.update', $interaction), [
            'contact_id' => $contact->id,
        ])->assertRedirect(route('phone-interactions.show', $interaction));

        $interaction->refresh();
        $this->assertTrue($interaction->contact->is($contact));
        $this->assertSame(PhoneInteractionMatchStatus::Matched, $interaction->match_status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'phone_contact_linked',
            'auditable_id' => $interaction->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_guest_cannot_view_phone_workspace(): void
    {
        $this->get(route('phone-interactions.index'))->assertRedirect(route('login'));
    }

    public function test_marketing_user_cannot_see_unmatched_or_restricted_contact_calls(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $surplusContact = Contact::factory()->create(['type' => ContactType::Surplus]);
        $normalContact = Contact::factory()->create(['type' => ContactType::Seller]);

        $unmatched = PhoneInteraction::factory()->create(['summary' => 'Unmatched private call']);
        $restricted = PhoneInteraction::factory()->create([
            'contact_id' => $surplusContact->id,
            'match_status' => PhoneInteractionMatchStatus::Matched,
            'summary' => 'Surplus private call',
        ]);
        $visible = PhoneInteraction::factory()->create([
            'contact_id' => $normalContact->id,
            'match_status' => PhoneInteractionMatchStatus::Matched,
            'summary' => 'Seller visible call',
        ]);

        $this->actingAs($marketing)->get(route('phone-interactions.index'))
            ->assertOk()
            ->assertSee('Seller visible call')
            ->assertDontSee('Unmatched private call')
            ->assertDontSee('Surplus private call');
        $this->actingAs($marketing)->get(route('phone-interactions.show', $unmatched))->assertForbidden();
        $this->actingAs($marketing)->get(route('phone-interactions.show', $restricted))->assertForbidden();
        $this->actingAs($marketing)->get(route('phone-interactions.show', $visible))->assertOk();
    }

    private function headers(): array
    {
        return ['X-VVR-Beside-Secret' => self::SECRET];
    }

    private function payload(): array
    {
        return [
            'event_id' => 'beside-call-1001',
            'event_type' => 'call',
            'direction' => 'inbound',
            'occurred_at' => '2026-08-16T10:30:00-04:00',
            'phone_number' => '+1 (407) 900-6554',
            'caller_name' => 'Test Caller',
            'duration_seconds' => 185,
            'summary' => 'Caller asked about a property.',
            'transcript' => 'Full confidential transcript.',
            'action_items' => ['Return the call'],
            'provider_payload' => ['beside_internal_field' => 'retained privately'],
        ];
    }
}
