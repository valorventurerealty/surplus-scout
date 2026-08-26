<?php

namespace Tests\Feature;

use App\Contracts\SurplusDocumentExtractionInterface;
use App\Data\SurplusExtractionResult;
use App\Enums\ContactType;
use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\Contact;
use App\Models\Property;
use App\Models\PropertyTaxRecord;
use App\Models\SurplusCase;
use App\Models\SurplusIntakeFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VvrAiSurplusIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['ai.provider' => 'gemini', 'ai.api_key' => 'test-key']);
    }

    public function test_owner_can_review_and_approve_prior_year_tax_notice_intake(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->mock(SurplusDocumentExtractionInterface::class)->shouldReceive('extract')->once()->andReturn($this->extraction());

        $response = $this->actingAs($user)->post(route('vvr-ai.intakes.store'), [
            'prompt' => 'Create a Surplus case from this prior-year tax notice.',
            'document' => UploadedFile::fake()->create('trim-notice.pdf', 100, 'application/pdf'),
            'acknowledge_external_processing' => '1',
        ]);

        $conversation = AiConversation::query()->sole();
        $intake = SurplusIntakeFile::query()->sole();
        $response->assertRedirect(route('vvr-ai.conversations.show', $conversation));
        $this->assertSame('create_surplus_case', $conversation->intent);
        $this->assertSame('awaiting_approval', $conversation->status);
        Storage::disk('local')->assertExists($intake->path);

        $this->get(route('vvr-ai.conversations.show', $conversation))->assertOk()
            ->assertSee('Surplus document-intake review')->assertSee('2.4900')
            ->assertSee('Annual tax-history amounts will not be copied')
            ->assertSee('332 Walter Drive');

        $this->post(route('vvr-ai.surplus-intakes.approve', $conversation), $this->approvalData($intake))
            ->assertRedirect(route('vvr-ai.conversations.show', $conversation));

        $property = Property::query()->sole();
        $contact = Contact::query()->sole();
        $case = SurplusCase::query()->sole();
        $tax = PropertyTaxRecord::query()->sole();
        $this->assertSame('2.4900', $property->acreage);
        $this->assertNull($property->taxes);
        $this->assertSame('Joyce', $contact->first_name);
        $this->assertSame('Di Tacchio', $contact->last_name);
        $this->assertSame(ContactType::Surplus, $contact->type);
        $this->assertSame('332 Walter Drive', $contact->mailing_address_line_1);
        $this->assertSame($property->id, $case->property_id);
        $this->assertSame($contact->id, $case->claimant_contact_id);
        $this->assertNull($case->surplus_amount);
        $this->assertSame('12.00', $case->agreed_fee_percentage);
        $this->assertSame(2025, $tax->tax_year);
        $this->assertSame('49.01', $tax->prior_year_final_tax);
        $this->assertSame(4, Task::query()->count());
        $this->assertSame('attached', $intake->fresh()->status);
        $this->assertSame('user_confirmed', data_get($intake->fresh()->extraction_json, 'approved_values.acreage.verification_status'));
        $this->assertSame('completed', $conversation->fresh()->status);
        $this->assertDatabaseHas('ai_usage_records', ['conversation_id' => $conversation->id, 'operation' => 'surplus_document_extraction', 'successful' => 1]);
        $this->assertDatabaseHas('ai_audit_logs', ['conversation_id' => $conversation->id, 'event' => 'surplus_intake_executed']);

        $this->get(route('surplus.intake-files.download', [$case, $intake]))->assertOk();
    }

    public function test_surplus_intake_cannot_execute_without_explicit_approval(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id, 'intent' => 'create_surplus_case']);
        $intake = SurplusIntakeFile::factory()->create(['user_id' => $user->id, 'ai_conversation_id' => $conversation->id]);

        $data = $this->approvalData($intake);
        unset($data['approve_extracted_data']);
        $this->actingAs($user)->post(route('vvr-ai.surplus-intakes.approve', $conversation), $data)
            ->assertSessionHasErrors('approve_extracted_data');

        $this->assertDatabaseCount('properties', 0);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('surplus_cases', 0);
        $this->assertSame('ready', $intake->fresh()->status);
    }

    public function test_trim_notice_filename_selects_surplus_intake_for_a_generic_prompt(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->mock(SurplusDocumentExtractionInterface::class)->shouldReceive('extract')->once()->andReturn($this->extraction());

        $this->actingAs($user)->post(route('vvr-ai.intakes.store'), [
            'prompt' => 'Extract the owner and property information from this file.',
            'document' => UploadedFile::fake()->create('Trim Notice 2025.pdf', 100, 'application/pdf'),
            'acknowledge_external_processing' => '1',
        ])->assertRedirect();

        $this->assertSame('create_surplus_case', AiConversation::query()->sole()->intent);
        $this->assertDatabaseCount('surplus_intake_files', 1);
        $this->assertDatabaseCount('property_intake_files', 0);
    }

    public function test_existing_records_can_be_selected_without_duplication_and_tasks_are_idempotent(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create(['first_name' => 'Joyce', 'last_name' => 'Di Tacchio']);
        $property = Property::factory()->create(['owner_contact_id' => $contact->id]);
        $case = SurplusCase::factory()->create(['property_id' => $property->id, 'claimant_contact_id' => $contact->id]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id, 'intent' => 'create_surplus_case']);
        $intake = SurplusIntakeFile::factory()->create(['user_id' => $user->id, 'ai_conversation_id' => $conversation->id]);
        $case->tasks()->create(['title' => 'Verify surplus amount', 'status' => 'pending', 'priority' => 'high', 'created_by' => $user->id]);

        $data = $this->approvalData($intake, [
            'property_resolution' => 'use_existing', 'property_id' => $property->id,
            'contact_resolution' => 'use_existing', 'contact_id' => $contact->id,
            'surplus_resolution' => 'use_existing', 'surplus_case_id' => $case->id,
        ]);
        $this->actingAs($user)->post(route('vvr-ai.surplus-intakes.approve', $conversation), $data)->assertRedirect();

        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('surplus_cases', 1);
        $this->assertDatabaseCount('tasks', 4);
        $this->assertDatabaseHas('property_tax_records', ['property_id' => $property->id, 'tax_year' => 2025]);
    }

    public function test_required_failure_rolls_back_every_related_write(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        Property::factory()->create([
            'parcel_id' => '3627316000000L1000', 'normalized_parcel_id' => '3627316000000L1000',
            'county' => 'Osceola', 'normalized_county' => 'OSCEOLA', 'state' => 'FL',
        ]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id, 'intent' => 'create_surplus_case']);
        $intake = SurplusIntakeFile::factory()->create(['user_id' => $user->id, 'ai_conversation_id' => $conversation->id]);

        $this->actingAs($user)->post(route('vvr-ai.surplus-intakes.approve', $conversation), $this->approvalData($intake))
            ->assertSessionHasErrors('property_resolution');

        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('surplus_cases', 0);
        $this->assertDatabaseCount('property_tax_records', 0);
        $this->assertSame('ready', $intake->fresh()->status);
    }

    public function test_unauthorized_roles_cannot_submit_private_surplus_intake(): void
    {
        foreach ([UserRole::VirtualAssistant, UserRole::ReadOnly, UserRole::Marketing] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('vvr-ai.intakes.store'), [
                'prompt' => 'Create a Surplus case from this TRIM notice.',
                'document' => UploadedFile::fake()->create('trim.pdf', 10, 'application/pdf'),
                'acknowledge_external_processing' => '1',
            ])->assertSessionHasErrors('document');
        }
    }

    public function test_marketing_cannot_discover_or_open_surplus_contact_mailing_address(): void
    {
        $contact = Contact::factory()->create([
            'type' => ContactType::Surplus, 'first_name' => 'Private', 'last_name' => 'Claimant',
            'mailing_address_line_1' => '123 Confidential Lane',
        ]);
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);

        $this->actingAs($marketing)->get(route('contacts.index'))->assertOk()->assertDontSee('Private Claimant');
        $this->get(route('contacts.show', $contact))->assertForbidden();
    }

    private function extraction(): SurplusExtractionResult
    {
        $fields = [
            ['parcel_id', '3627316000000L1000', .99, 1, 'Parcel ID 3627316000000L1000'],
            ['county', 'Osceola', .99, 1, 'Osceola County'], ['address', 'Holopaw Groves Rd', .97, 1, 'HOLOPAW GROVES RD'],
            ['city', 'Saint Cloud', .96, 1, 'SAINT CLOUD'], ['state', 'FL', .99, 1, 'FL'],
            ['property_type', 'land', .95, 1, 'Fire Rescue Vacant Land'], ['acreage', '2.4900', .96, 2, 'Units 2.4900'],
            ['legal_description', 'BEG 660 FT N & 660 FT E OF SW COR GOV LOT 1', .9, 1, 'BEG 660 FT N'],
            ['owner_first_name', 'Joyce', .94, 1, 'DI TACCHIO JOYCE L'], ['owner_last_name', 'Di Tacchio', .94, 1, 'DI TACCHIO JOYCE L'],
            ['mailing_address_line_1', '332 Walter Drive', .98, 1, '332 WALTER DRIVE'], ['mailing_city', 'Keswick', .98, 1, 'KESWICK'],
            ['mailing_state_province', 'ON', .98, 1, 'ON L4P 3A7'], ['mailing_postal_code', 'L4P 3A7', .98, 1, 'L4P 3A7'],
            ['mailing_country', 'Canada', .99, 1, 'CANADA'], ['tax_year', '2025', .99, 1, '2025 Notice'],
            ['market_value', '3600', .99, 1, 'Market Value 3,600'], ['assessed_value', '3600', .99, 1, 'Assessed Value 3,600'],
            ['taxable_value', '3600', .99, 1, 'Taxable Value 3,600'], ['prior_year_final_tax', '49.01', .99, 1, '2024 Final Tax 49.01'],
            ['proposed_tax', '49.88', .99, 1, '2025 Proposed 49.88'], ['non_ad_valorem_assessments', '.35', .99, 2, 'Assessment .35'],
            ['assessment_classification', 'Fire Rescue Vacant Land', .99, 2, 'Fire Rescue Vacant Land'],
        ];
        return SurplusExtractionResult::fromArray([
            'fields' => array_map(fn ($field) => ['field' => $field[0], 'value' => $field[1], 'confidence' => $field[2], 'page' => $field[3], 'source_excerpt' => $field[4], 'verification_status' => 'extracted'], $fields),
            'missing_fields' => ['surplus_amount', 'foreclosure_case_number', 'certificate_number', 'sale_date', 'claim_deadline'],
            'warnings' => ['Surplus amount is not present in this tax notice.'],
        ], 'gemini-test', 250, 120);
    }

    private function approvalData(SurplusIntakeFile $intake, array $overrides = []): array
    {
        return array_merge([
            'intake_token' => $intake->token, 'approve_extracted_data' => '1',
            'property_resolution' => 'create', 'parcel_id' => '3627316000000L1000', 'county' => 'Osceola',
            'address' => 'Holopaw Groves Rd', 'city' => 'Saint Cloud', 'state' => 'FL', 'property_type' => 'land', 'acreage' => '2.4900',
            'legal_description' => 'BEG 660 FT N & 660 FT E OF SW COR GOV LOT 1',
            'contact_resolution' => 'create', 'first_name' => 'Joyce', 'last_name' => 'Di Tacchio',
            'mailing_address_line_1' => '332 Walter Drive', 'mailing_city' => 'Keswick', 'mailing_state_province' => 'ON',
            'mailing_postal_code' => 'L4P 3A7', 'mailing_country' => 'Canada',
            'surplus_resolution' => 'create', 'tax_year' => 2025, 'market_value' => 3600,
            'assessed_value' => 3600, 'taxable_value' => 3600, 'prior_year_final_tax' => 49.01,
            'proposed_tax' => 49.88, 'non_ad_valorem_assessments' => .35,
            'assessment_classification' => 'Fire Rescue Vacant Land', 'create_research_tasks' => '1',
        ], $overrides);
    }
}
