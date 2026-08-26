<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\ContactIntakeFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactDocumentIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_contact_autofill_is_not_visible_or_routable(): void
    {
        Http::fake();
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get(route('contacts.create'))
            ->assertOk()
            ->assertDontSee('Autofill from a document')
            ->assertDontSee('Upload & autofill')
            ->assertDontSee('contact_document');

        $this->actingAs($user)->post('/contacts/intake/extract')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_historical_attached_contact_document_remains_private_and_downloadable(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $assistant = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $contact = Contact::factory()->create();
        Storage::disk('local')->put('contact-intakes/source.pdf', 'private contact source');
        $file = ContactIntakeFile::query()->create([
            'token' => fake()->uuid(),
            'user_id' => $owner->id,
            'contact_id' => $contact->id,
            'disk' => 'local',
            'path' => 'contact-intakes/source.pdf',
            'original_name' => 'source.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 22,
            'sha256' => hash('sha256', 'private contact source'),
            'status' => 'attached',
            'attached_at' => now(),
        ]);

        $this->actingAs($assistant)->get(route('contacts.intake-files.download', [$contact, $file]))
            ->assertForbidden();
        $this->actingAs($owner)->get(route('contacts.intake-files.download', [$contact, $file]))
            ->assertDownload('source.pdf');
    }
}
