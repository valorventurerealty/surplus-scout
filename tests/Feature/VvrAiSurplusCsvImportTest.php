<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\AiSurplusCsvImport;
use App\Models\AiSurplusCsvImportRow;
use App\Models\Contact;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VvrAiSurplusCsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_owner_can_review_and_approve_contacts_and_surplus_cases_from_csv(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $response = $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'prompt' => 'Import this Orange County Surplus mailing list.',
            'csv_file' => UploadedFile::fake()->createWithContent('Orange County Stannp.csv', $this->csv()),
        ]);

        $conversation = AiConversation::query()->sole();
        $import = AiSurplusCsvImport::query()->sole();
        $response->assertRedirect(route('vvr-ai.conversations.show', $conversation));
        $this->assertSame('create_surplus_contacts_from_csv', $conversation->intent);
        $this->assertSame('Orange', $import->default_county);
        $this->assertSame(2, $import->row_count);
        $this->assertSame(2, $import->valid_row_count);
        Storage::disk('local')->assertExists($import->path);

        $this->get(route('vvr-ai.conversations.show', $conversation))->assertOk()
            ->assertSee('Surplus CSV import plan')->assertSee('Ji Li')->assertSee('$887.38')
            ->assertSee("claimant's mailing state");

        $rows = AiSurplusCsvImportRow::query()->pluck('id')->all();
        $this->post(route('vvr-ai.surplus-csv-imports.approve', [$conversation, $import]), [
            'case_state' => 'FL', 'county' => 'Orange', 'selected_rows' => $rows,
        ])->assertRedirect(route('vvr-ai.conversations.show', $conversation));

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('surplus_cases', 2);
        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseCount('properties', 0);
        $contact = Contact::query()->sole();
        $this->assertSame(ContactType::Surplus, $contact->type);
        $this->assertSame('130 HIGHLAND RD', $contact->mailing_address_line_1);
        $this->assertSame('NY', $contact->mailing_state_province);
        $this->assertSame('887.38', SurplusCase::query()->where('parcel_id', '31-22-33-1332-09-090')->sole()->expected_fee);
        $this->assertSame('completed', $import->fresh()->status);
        $this->assertSame('completed', $conversation->fresh()->status);
        $this->assertDatabaseHas('ai_audit_logs', ['event' => 'surplus_csv_import_executed']);
    }

    public function test_existing_contact_is_reused_and_existing_case_is_skipped(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create([
            'first_name' => 'Ji', 'last_name' => 'Li', 'mailing_address_line_1' => '130 HIGHLAND RD',
            'mailing_city' => 'SCARSDALE', 'mailing_state_province' => 'NY', 'mailing_postal_code' => '10583',
        ]);
        SurplusCase::factory()->create(['claimant_contact_id' => $contact->id, 'state' => 'FL', 'county' => 'Orange', 'parcel_id' => '31-22-33-1332-09-090']);

        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'prompt' => 'Import Orange County.', 'csv_file' => UploadedFile::fake()->createWithContent('mail.csv', $this->csv()),
        ])->assertRedirect();
        $conversation = AiConversation::query()->latest('id')->firstOrFail();
        $import = AiSurplusCsvImport::query()->sole();
        $row = $import->rows()->where('parcel_id', '25-24-28-5844-00-691')->sole();

        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.approve', [$conversation, $import]), [
            'case_state' => 'FL', 'county' => 'Orange', 'selected_rows' => [$row->id],
        ])->assertRedirect();

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('surplus_cases', 2);
        $this->assertSame($contact->id, SurplusCase::query()->where('parcel_id', $row->parcel_id)->sole()->claimant_contact_id);
    }

    public function test_same_name_with_different_addresses_creates_one_contact_for_multiple_parcels(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $csv = implode("\n", [
            'firstname,lastname,address1,city,State,country,postcode,parcel_number,Surplus',
            'Gerry,Young,11765 STONEWALL SPRINGS AVE,LAS VEGAS,NV,US,89138,25-22-32-6215-02-880,4880',
            'Gerry,Young,11766 STONEWALL SPRINGS AVE,LAS VEGAS,NV,US,89138,25-22-32-6215-02-590,3369',
            'Gerry,Young,11767 STONEWALL SPRINGS AVE,LAS VEGAS,NV,US,89138,25-22-32-6215-02-470,5269',
        ])."\n";

        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'prompt' => 'Import this Orange County list.',
            'csv_file' => UploadedFile::fake()->createWithContent('Orange County Stannp.csv', $csv),
        ])->assertRedirect();

        $conversation = AiConversation::query()->sole();
        $import = AiSurplusCsvImport::query()->sole();
        $this->get(route('vvr-ai.conversations.show', $conversation))->assertOk()
            ->assertSee('3 parcels for this contact')
            ->assertSee('Different addresses appear for this name');

        $this->post(route('vvr-ai.surplus-csv-imports.approve', [$conversation, $import]), [
            'case_state' => 'FL', 'county' => 'Orange',
            'selected_rows' => $import->rows()->pluck('id')->all(),
        ])->assertRedirect();

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('surplus_cases', 3);
        $contact = Contact::query()->sole();
        $this->assertSame('11765 STONEWALL SPRINGS AVE', $contact->mailing_address_line_1);
        $this->assertSame(3, $contact->surplusCases()->count());
        $this->assertSame(1, count($import->fresh()->result_json['created_contacts']));
        $this->assertSame(0, count($import->fresh()->result_json['reused_contacts']));
    }

    public function test_tax_deed_export_headers_are_mapped_and_created_after_approval(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $csv = implode("\n", [
            'Sale Date,Tax Deed #,Cert #,Surplus Available,Property ID #,firstname,lastname,address1,city,State,country,postcode',
            '02/17/2026,256-2025,19242023,"$68,973.87",112528370000220050,Daniel,Benson,5116 PALMETTO RD,KISSIMMEE,FL,US,34746-5221',
            '01/28/2025,191-2024,32732022,"$60,365.78",182529185600016080,Gao Xi,Qing,4137 SOUTHERN OAKS CT #608,KISSIMMEE,FL,US,34741',
        ])."\n";

        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'prompt' => 'Import this Osceola County tax deed surplus list.',
            'csv_file' => UploadedFile::fake()->createWithContent('Osceola County tax deeds.csv', $csv),
        ])->assertRedirect();

        $conversation = AiConversation::query()->sole();
        $import = AiSurplusCsvImport::query()->sole();
        $this->assertSame('Osceola', $import->default_county);
        $this->get(route('vvr-ai.conversations.show', $conversation))->assertOk()
            ->assertSee('256-2025')->assertSee('19242023')->assertSee('02/17/2026')
            ->assertSee('$8,276.86');

        $this->post(route('vvr-ai.surplus-csv-imports.approve', [$conversation, $import]), [
            'case_state' => 'FL', 'county' => 'Osceola',
            'selected_rows' => $import->rows()->pluck('id')->all(),
        ])->assertRedirect();

        $this->assertDatabaseCount('contacts', 2);
        $this->assertDatabaseCount('surplus_cases', 2);
        $this->assertDatabaseCount('properties', 0);
        $daniel = SurplusCase::query()->where('parcel_id', '112528370000220050')->sole();
        $this->assertSame('256-2025', $daniel->tax_deed_number);
        $this->assertSame('19242023', $daniel->certificate_number);
        $this->assertSame('2026-02-17', $daniel->sale_date->toDateString());
        $this->assertSame('68973.87', $daniel->surplus_amount);
        $this->assertSame('8276.86', $daniel->expected_fee);
        $this->assertDatabaseHas('contacts', ['first_name' => 'Gao Xi', 'last_name' => 'Qing']);
    }

    public function test_country_names_are_normalized_without_crashing_the_staging_import(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $csv = implode("\n", [
            'Sale Date,Tax Deed #,Cert #,Surplus Available,Property ID #,firstname,lastname,address1,city,State,country,postcode',
            '02/17/2026,256-2025,19242023,"$68,973.87",112528370000220050,Daniel,Benson,5116 PALMETTO RD,KISSIMMEE,Florida,United States,34746-5221',
        ])."\n";

        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'prompt' => 'Import Osceola County.',
            'csv_file' => UploadedFile::fake()->createWithContent('Osceola County.csv', $csv),
        ])->assertRedirect();

        $row = AiSurplusCsvImportRow::query()->sole();
        $this->assertSame('ready', $row->status);
        $this->assertSame('FL', $row->mailing_state);
        $this->assertSame('US', $row->mailing_country);
    }

    public function test_unquoted_currency_comma_is_reported_as_an_invalid_row_instead_of_crashing(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $csv = implode("\n", [
            'Sale Date,Tax Deed #,Cert #,Surplus Available,Property ID #,firstname,lastname,address1,city,State,country,postcode',
            '02/17/2026,256-2025,19242023,$68,973.87,112528370000220050,Daniel,Benson,5116 PALMETTO RD,KISSIMMEE,FL,US,34746-5221',
        ])."\n";

        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'prompt' => 'Import Osceola County.',
            'csv_file' => UploadedFile::fake()->createWithContent('Osceola County.csv', $csv),
        ])->assertRedirect();

        $row = AiSurplusCsvImportRow::query()->sole();
        $this->assertSame('invalid', $row->status);
        $this->assertStringContainsString('unquoted comma', implode(' ', $row->errors_json));
    }

    public function test_skip_trace_csv_updates_existing_case_creates_relative_and_links_contacts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $owner = Contact::factory()->create([
            'first_name' => 'Daniel', 'last_name' => 'Benson', 'phone' => null, 'email' => null,
        ]);
        $case = SurplusCase::factory()->create([
            'claimant_contact_id' => $owner->id, 'state' => 'FL', 'county' => 'Osceola County',
            'parcel_id' => '112528370000220050', 'surplus_amount' => 100,
            'agreed_fee_percentage' => 12, 'expected_fee' => 12,
        ]);
        $csv = implode("\n", [
            'firstname,lastname,address1,city,State,country,postcode,Surplus,Property ID #,Phone 1 number,Email 1,RELATIVE 1: First Name,RELATIVE 1: Last Name,RELATIVE 1: Possible Type,RELATIVE 1: Age,RELATIVE 1: Mailing Street,RELATIVE 1: Mailing City,RELATIVE 1: Mailing State,RELATIVE 1: Mailing ZIP Code,RELATIVE 1: Phone 1 number,RELATIVE 1: Phone 1 type,RELATIVE 1: Email 1',
            'Daniel,Benson,5116 PALMETTO RD,KISSIMMEE,FL,US,34746-5221,"$68,973.87",112528370000220050,407-361-7021,DANIEL@EXAMPLE.COM,JANET,BENSON,Spouse,75,1810 MONTE CRISTO LN,KISSIMMEE,FL,34758,4077857484,Mobile,JANET@EXAMPLE.COM',
        ])."\n";

        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'prompt' => 'Update this Osceola County skip trace.',
            'csv_file' => UploadedFile::fake()->createWithContent('Osceola Skip Trace.csv', $csv),
        ])->assertRedirect();
        $conversation = AiConversation::query()->sole();
        $import = AiSurplusCsvImport::query()->sole();
        $this->get(route('vvr-ai.conversations.show', $conversation))->assertOk()
            ->assertSee('JANET BENSON')->assertSee('Update and link');

        $this->post(route('vvr-ai.surplus-csv-imports.approve', [$conversation, $import]), [
            'case_state' => 'FL', 'county' => 'Osceola',
            'selected_rows' => $import->rows()->pluck('id')->all(),
        ])->assertRedirect();

        $this->assertDatabaseCount('surplus_cases', 1);
        $this->assertSame('68973.87', $case->fresh()->surplus_amount);
        $this->assertSame('8276.86', $case->fresh()->expected_fee);
        $this->assertSame('407-361-7021', $owner->fresh()->phone);
        $relative = Contact::query()->where('first_name', 'JANET')->where('last_name', 'BENSON')->sole();
        $this->assertSame('4077857484', $relative->phone);
        $this->assertSame('janet@example.com', $relative->email);
        $this->assertDatabaseHas('contact_surplus_case', [
            'surplus_case_id' => $case->id, 'contact_id' => $relative->id, 'role' => 'relative',
            'relationship_notes' => 'Spouse · Reported age 75',
        ]);
        $this->assertCount(1, $import->fresh()->result_json['updated_cases']);
        $this->assertCount(1, $import->fresh()->result_json['linked_contacts']);
    }

    public function test_invalid_headers_and_rows_do_not_create_records(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent('bad.csv', "firstname,lastname\nJi,Li\n"),
        ])->assertSessionHasErrors('csv_file');
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('surplus_cases', 0);
    }

    public function test_user_without_surplus_financial_permission_cannot_upload_csv(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $this->actingAs($user)->post(route('vvr-ai.surplus-csv-imports.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent('mail.csv', $this->csv()),
        ])->assertForbidden();
        $this->assertDatabaseCount('ai_surplus_csv_imports', 0);
    }

    private function csv(): string
    {
        return implode("\n", [
            'firstname,lastname,address1,city,State,country,postcode,parcel_number,Surplus',
            'Ji,Li,130 HIGHLAND RD,SCARSDALE,NY,US,10583,31-22-33-1332-09-090,7394.85',
            'Ji,Li,130 HIGHLAND RD,SCARSDALE,NY,US,10583,25-24-28-5844-00-691,9031.07',
        ])."\n";
    }
}
