<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\AiPreAuctionCsvImport;
use App\Models\Contact;
use App\Models\PreAuctionAcquisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VvrAiPreAuctionCsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_owner_can_review_and_approve_the_supplied_pre_auction_csv_format(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $response = $this->actingAs($user)->post(route('vvr-ai.pre-auction-csv-imports.store'), [
            'prompt' => 'Create Pre-Auction files from this CSV.',
            'csv_file' => UploadedFile::fake()->createWithContent('Orange Osceola Polk Marion.csv', $this->csv()),
        ]);

        $conversation = AiConversation::query()->sole();
        $import = AiPreAuctionCsvImport::query()->sole();
        $response->assertRedirect(route('vvr-ai.conversations.show', $conversation));
        $this->assertSame('create_pre_auction_acquisitions_from_csv', $conversation->intent);
        $this->assertSame(1, $import->row_count);
        $this->assertSame(1, $import->valid_row_count);
        Storage::disk('local')->assertExists($import->path);

        $this->get(route('vvr-ai.conversations.show', $conversation))->assertOk()
            ->assertSee('PreTax Auctions CSV import plan')->assertSee('Harris A TYRELL')
            ->assertSee('2125291900000d0090')->assertSee('Sep 15, 2026')
            ->assertSee('mailing address belongs to the contact', false);

        $this->post(route('vvr-ai.pre-auction-csv-imports.approve', [$conversation, $import]), [
            'selected_rows' => $import->rows()->pluck('id')->all(),
        ])->assertRedirect(route('vvr-ai.conversations.show', $conversation));

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('pre_auction_acquisitions', 1);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('properties', 0);
        $this->assertDatabaseCount('calendar_events', 0);
        $contact = Contact::query()->sole();
        $case = PreAuctionAcquisition::query()->sole();
        $this->assertSame(ContactType::PreTaxAuctions, $contact->type);
        $this->assertSame('930 CYPRESS AVENUE', $contact->mailing_address_line_1);
        $this->assertSame('Atyrell Enterprises Llc', $contact->company);
        $this->assertSame('Osceola', $case->county);
        $this->assertSame('2125291900000d0090', $case->parcel_id);
        $this->assertSame('2026-09-15', $case->auction_at->toDateString());
        $this->assertSame('0.00', $case->assessor_market_value);
        $this->assertNull($case->projected_surplus);
        $this->assertSame('completed', $import->fresh()->status);
        $this->assertDatabaseHas('ai_audit_logs', ['event' => 'pre_auction_csv_import_executed']);
    }

    public function test_reimport_reuses_contact_case_and_task_idempotently(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($user)->post(route('vvr-ai.pre-auction-csv-imports.store'), [
                'prompt' => 'Import Pre-Auction list.',
                'csv_file' => UploadedFile::fake()->createWithContent('list.csv', $this->csv()),
            ])->assertRedirect();
            $conversation = AiConversation::query()->latest('id')->firstOrFail();
            $import = AiPreAuctionCsvImport::query()->latest('id')->firstOrFail();
            $this->actingAs($user)->post(route('vvr-ai.pre-auction-csv-imports.approve', [$conversation, $import]), [
                'selected_rows' => $import->rows()->pluck('id')->all(),
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('pre_auction_acquisitions', 1);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_read_only_user_cannot_start_pre_auction_csv_writes(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $this->actingAs($user)->post(route('vvr-ai.pre-auction-csv-imports.store'), [
            'prompt' => 'Import Pre-Auction list.',
            'csv_file' => UploadedFile::fake()->createWithContent('list.csv', $this->csv()),
        ])->assertForbidden();
        $this->assertDatabaseCount('ai_pre_auction_csv_imports', 0);
    }

    private function csv(): string
    {
        return implode("\n", [
            'firstname,lastname,address1,city,State,country,postcode,Listing Type,Assessor Market Value,v,Parcel Number,Appraiser Link,County,Owner 1 Name,Property Details Link',
            'Harris A,TYRELL,930 CYPRESS AVENUE,WINTER PARK,FL,US,32789,Tax Deed,$0,2026-09-15,2125291900000d0090,https://search.property-appraiser.org/Search/MainSearch,Osceola,Atyrell Enterprises Llc,https://propertyonion.com/property_search/properties/Land-2125291900000d0090-Kissimmee-Fl-34741/1284460',
        ])."\n";
    }
}
