<?php

namespace Tests\Feature;

use App\Enums\ArmoryEmailTemplateStatus;
use App\Enums\OutboundEmailStatus;
use App\Enums\UserRole;
use App\Jobs\SendOutboundEmailJob;
use App\Mail\VvrOutboundMessage;
use App\Models\Contact;
use App\Models\ArmoryEmailTemplate;
use App\Models\ArmoryEmailTemplateAttachment;
use App\Models\EmailSignature;
use App\Models\OutboundEmail;
use App\Models\OutboundEmailAttachment;
use App\Models\Property;
use App\Models\PreAuctionAcquisition;
use App\Models\SurplusCase;
use App\Models\User;
use App\Services\OutboundEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_saves_draft_without_sending(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['email' => 'seller@example.com']);
        $response = $this->actingAs($user)->post(route('email.store'), $this->draftData($contact));
        $email = OutboundEmail::query()->firstOrFail();
        $response->assertRedirect(route('email.show', $email));
        $this->assertSame(OutboundEmailStatus::Draft, $email->status);
        Mail::assertNothingSent();
    }

    public function test_composer_displays_the_complete_merge_field_registry(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($user)->get(route('email.create'));

        $response->assertOk();
        foreach (array_keys(config('email.merge_fields')) as $field) {
            $response->assertSee($field);
        }
        $response->assertSee('Missing values remain visible and block delivery');
    }

    public function test_composer_exposes_hyperlinks_and_private_attachments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('email.create'))
            ->assertOk()
            ->assertSee('Insert a hyperlink')
            ->assertSee('name="attachments[]"', false)
            ->assertSee('multiple', false);
    }

    public function test_signature_editor_creates_safe_clickable_links(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $signature = EmailSignature::query()->where('is_default', true)->firstOrFail();

        $this->actingAs($user)->get(route('email.signatures.edit', $signature))
            ->assertOk()
            ->assertSee('Insert a hyperlink');

        $this->actingAs($user)->post(route('email.signatures.update', $signature), [
            'name' => 'Updated signature',
            'body_text' => "[Visit Valor Venture](https://valorventure.us/)\nSchedule: https://valorventure.us/meetings/valorventurerealty\n<script>alert('unsafe')</script>",
            'is_default' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('email.signatures.index'));

        $signature->refresh();
        $this->assertStringContainsString('<a href="https://valorventure.us/">Visit Valor Venture</a>', $signature->body_html);
        $this->assertStringContainsString('<a href="https://valorventure.us/meetings/valorventurerealty">https://valorventure.us/meetings/valorventurerealty</a>', $signature->body_html);
        $this->assertStringNotContainsString('<script', $signature->body_html);

        $this->actingAs($user)->get(route('email.signatures.index'))
            ->assertOk()
            ->assertSee('<a href="https://valorventure.us/">Visit Valor Venture</a>', false);
    }

    public function test_surplus_case_identifiers_are_authoritative_over_linked_property_values(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['first_name' => 'Daniel', 'email' => 'daniel@example.com']);
        $property = Property::factory()->create(['parcel_id' => 'PROPERTY-999', 'county' => 'Orange', 'owner_contact_id' => $contact->id]);
        $case = SurplusCase::factory()->create(['property_id' => $property->id, 'claimant_contact_id' => $contact->id, 'parcel_id' => '112528370000220050', 'county' => 'Osceola']);

        $this->actingAs($user)->post(route('email.store'), $this->draftData($contact, [
            'subject' => 'Case {{parcel_id}}',
            'body_text' => 'Parcel {{parcel_id}} in {{county}} County',
            'related_record' => 'surplus:'.$case->id,
        ]))->assertRedirect();

        $email = OutboundEmail::query()->firstOrFail();
        $preview = app(OutboundEmailService::class)->preview($email);
        $this->assertSame('Case 112528370000220050', $preview['subject']);
        $this->assertStringContainsString('Parcel 112528370000220050 in Osceola County', $preview['text']);
        $this->assertSame([], $preview['unresolved']);
    }

    public function test_pre_tax_auction_case_is_available_as_authoritative_email_context(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['first_name' => 'Taylor', 'email' => 'taylor@example.com']);
        $property = Property::factory()->create(['parcel_id' => 'PROPERTY-111', 'county' => 'Orange', 'owner_contact_id' => $contact->id]);
        $case = PreAuctionAcquisition::factory()->create([
            'property_id' => $property->id,
            'owner_contact_id' => $contact->id,
            'parcel_id' => 'PRETAX-222',
            'county' => 'Osceola',
        ]);

        $this->actingAs($user)->get(route('email.create'))->assertOk()->assertSee('PreTax Auction');
        $this->actingAs($user)->post(route('email.store'), $this->draftData($contact, [
            'subject' => 'File {{case_number}}',
            'body_text' => 'Parcel {{parcel_id}} in {{county}} County',
            'related_record' => 'pre_auction:'.$case->id,
        ]))->assertRedirect();

        $preview = app(OutboundEmailService::class)->preview(OutboundEmail::query()->firstOrFail());
        $this->assertSame('File '.$case->case_number, $preview['subject']);
        $this->assertStringContainsString('Parcel PRETAX-222 in Osceola County', $preview['text']);
        $this->assertSame([], $preview['unresolved']);
    }

    public function test_send_requires_confirmation_and_queues_exact_draft(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['first_name' => 'Daniel', 'email' => 'daniel@example.com']);
        $this->actingAs($user)->post(route('email.store'), $this->draftData($contact, ['subject' => 'Hello {{first_name}}']))->assertRedirect();
        $email = OutboundEmail::query()->firstOrFail();
        $this->actingAs($user)->post(route('email.send', $email), [])->assertSessionHasErrors('confirm_send');
        $fingerprint = app(OutboundEmailService::class)->preview($email)['fingerprint'];
        $this->actingAs($user)->post(route('email.send', $email), ['confirm_send' => '1', 'review_fingerprint' => $fingerprint])->assertRedirect();
        $email->refresh();
        $this->assertSame(OutboundEmailStatus::Queued, $email->status);
        $this->assertSame('Hello Daniel', $email->subject);
        $this->assertStringContainsString('Mark Lewis, MBA', $email->final_text);
        Queue::assertPushed(SendOutboundEmailJob::class, fn ($job) => $job->outboundEmailId === $email->id);
    }

    public function test_safe_hyperlink_is_rendered_in_html_and_unsafe_scheme_is_blocked(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['email' => 'seller@example.com']);
        $body = "First line\nSecond line\n\n[View property](https://valorventure.us/property/123)\n\n[Unsafe](javascript:alert(1))";

        $this->actingAs($user)->post(route('email.store'), $this->draftData($contact, ['body_text' => $body]))->assertRedirect();
        $email = OutboundEmail::query()->firstOrFail();
        $preview = app(OutboundEmailService::class)->preview($email);

        $this->assertStringContainsString('<a href="https://valorventure.us/property/123">View property</a>', $preview['html']);
        $this->assertStringContainsString("First line<br>\nSecond line", $preview['html']);
        $this->assertStringNotContainsString('javascript:', $preview['html']);

        $this->actingAs($user)->post(route('email.send', $email), ['confirm_send' => '1', 'review_fingerprint' => $preview['fingerprint']])->assertRedirect();
        $this->assertStringContainsString('<a href="https://valorventure.us/property/123">View property</a>', $email->fresh()->final_html);
    }

    public function test_read_only_user_cannot_compose_or_send(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $contact = Contact::factory()->create();
        $this->actingAs($user)->get(route('email.create'))->assertForbidden();
        $this->actingAs($user)->post(route('email.store'), $this->draftData($contact))->assertForbidden();
    }

    public function test_marketing_user_cannot_use_restricted_surplus_context(): void
    {
        $user = User::factory()->create(['role' => UserRole::Marketing]);
        $case = SurplusCase::factory()->create();
        $this->actingAs($user)->get(route('email.create', ['related_type' => 'surplus', 'related_id' => $case->id]))->assertForbidden();
    }

    public function test_marketing_user_cannot_use_restricted_pre_tax_auction_context(): void
    {
        $user = User::factory()->create(['role' => UserRole::Marketing]);
        $case = PreAuctionAcquisition::factory()->create();

        $this->actingAs($user)->get(route('email.create', ['related_type' => 'pre_auction', 'related_id' => $case->id]))->assertForbidden();
    }

    public function test_unresolved_merge_field_blocks_delivery(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create();
        $this->actingAs($user)->post(route('email.store'), $this->draftData($contact, ['body_text' => 'Unknown {{not_allowed}}']))->assertRedirect();
        $email = OutboundEmail::query()->firstOrFail();
        $fingerprint = app(OutboundEmailService::class)->preview($email)['fingerprint'];
        $this->actingAs($user)->post(route('email.send', $email), ['confirm_send' => '1', 'review_fingerprint' => $fingerprint])->assertSessionHasErrors('send');
        $this->assertSame(OutboundEmailStatus::Draft, $email->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_private_attachment_is_stored_and_policy_protected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $other = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $contact = Contact::factory()->create();
        $this->actingAs($owner)->post(route('email.store'), $this->draftData($contact, ['attachments' => [UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf')]]));
        $email = OutboundEmail::query()->firstOrFail();
        $attachment = $email->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($other)->get(route('email.attachments.download', [$email, $attachment]))->assertForbidden();
        $this->actingAs($owner)->get(route('email.attachments.download', [$email, $attachment]))->assertOk();
    }

    public function test_service_validation_returns_new_draft_to_compose_with_visible_errors(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['email' => 'seller@example.com']);

        $this->actingAs($user)
            ->from(route('email.index'))
            ->post(route('email.store'), $this->draftData($contact, ['to' => 'not-an-email']))
            ->assertRedirect(route('email.create'))
            ->assertSessionHasErrors('to')
            ->assertSessionHasInput('subject', 'VVR follow-up');

        $this->assertDatabaseCount('outbound_emails', 0);
    }

    public function test_selected_template_copies_its_private_attachment_into_the_draft(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['email' => 'seller@example.com']);
        $template = ArmoryEmailTemplate::factory()->create(['status' => ArmoryEmailTemplateStatus::Active]);
        Storage::disk('local')->put("armory-email-template/{$template->token}/offer.pdf", 'approved offer');
        $source = ArmoryEmailTemplateAttachment::query()->create(['token' => fake()->uuid(), 'armory_email_template_id' => $template->id, 'disk' => 'local', 'path' => "armory-email-template/{$template->token}/offer.pdf", 'original_name' => 'offer.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 14, 'sha256' => hash('sha256', 'approved offer'), 'uploaded_by' => $user->id]);

        $this->actingAs($user)->post(route('email.store'), $this->draftData($contact, ['armory_email_template_id' => $template->id]))->assertRedirect();

        $attachment = OutboundEmail::query()->firstOrFail()->attachments()->firstOrFail();
        $this->assertSame($source->id, $attachment->armory_email_template_attachment_id);
        $this->assertSame('offer.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_delivery_job_marks_message_sent(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $email = OutboundEmail::query()->create(['token' => fake()->uuid(), 'user_id' => $user->id, 'status' => OutboundEmailStatus::Queued, 'from_address' => 'info@valorventure.us', 'from_name' => 'Valor Venture Realty', 'to_json' => ['recipient@example.com'], 'subject' => 'Test', 'body_text' => 'Test', 'final_text' => 'Test', 'final_html' => '<div>Test</div>', 'queued_at' => now()]);
        (new SendOutboundEmailJob($email->id))->handle();
        $this->assertSame(OutboundEmailStatus::Sent, $email->fresh()->status);
        Mail::assertSent(VvrOutboundMessage::class);
    }

    public function test_author_can_soft_delete_an_unsent_draft_but_not_a_sent_email(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $draft = OutboundEmail::factory()->create(['user_id' => $user->id, 'status' => OutboundEmailStatus::Draft]);
        $sent = OutboundEmail::factory()->create(['user_id' => $user->id, 'status' => OutboundEmailStatus::Sent, 'sent_at' => now()]);

        $this->actingAs($user)->delete(route('email.destroy', $draft))->assertRedirect(route('email.index'));
        $this->assertSoftDeleted('outbound_emails', ['id' => $draft->id]);

        $this->actingAs($user)->delete(route('email.destroy', $sent))->assertForbidden();
        $this->assertDatabaseHas('outbound_emails', ['id' => $sent->id, 'deleted_at' => null]);
    }

    public function test_expired_deleted_draft_and_private_attachments_are_permanently_pruned(): void
    {
        Storage::fake('local');
        $draft = OutboundEmail::factory()->create(['status' => OutboundEmailStatus::Draft]);
        Storage::disk('local')->put('outbound-email/test/agreement.pdf', 'private document');
        OutboundEmailAttachment::query()->create(['token' => fake()->uuid(), 'outbound_email_id' => $draft->id, 'disk' => 'local', 'path' => 'outbound-email/test/agreement.pdf', 'original_name' => 'agreement.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 16, 'sha256' => hash('sha256', 'private document')]);
        $draft->delete();
        OutboundEmail::onlyTrashed()->whereKey($draft->id)->update(['deleted_at' => now()->subDays(31)]);

        $this->artisan('email:prune-deleted-drafts')->assertSuccessful();

        $this->assertDatabaseMissing('outbound_emails', ['id' => $draft->id]);
        $this->assertDatabaseMissing('outbound_email_attachments', ['outbound_email_id' => $draft->id]);
        Storage::disk('local')->assertMissing('outbound-email/test/agreement.pdf');
    }

    private function draftData(Contact $contact, array $overrides = []): array
    {
        $signature = EmailSignature::query()->where('is_default', true)->firstOrFail();
        return array_merge(['to' => $contact->email, 'cc' => '', 'bcc' => '', 'subject' => 'VVR follow-up', 'body_text' => 'Hello {{first_name}}', 'primary_contact_id' => $contact->id, 'email_signature_id' => $signature->id], $overrides);
    }
}
