<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurplusContactMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_previews_then_merges_exact_name_surplus_contacts_and_associations(): void
    {
        $canonical = Contact::factory()->create(['type' => ContactType::Surplus, 'first_name' => 'Gerry', 'last_name' => 'Young', 'mailing_address_line_1' => null]);
        $duplicateOne = Contact::factory()->create(['type' => ContactType::Surplus, 'first_name' => 'GERRY', 'last_name' => 'YOUNG', 'mailing_address_line_1' => '11765 STONEWALL SPRINGS AVE']);
        $duplicateTwo = Contact::factory()->create(['type' => ContactType::Surplus, 'first_name' => ' Gerry ', 'last_name' => ' Young ']);
        $caseOne = SurplusCase::factory()->create(['claimant_contact_id' => $duplicateOne->id, 'parcel_id' => 'PARCEL-1']);
        $caseTwo = SurplusCase::factory()->create(['claimant_contact_id' => $duplicateTwo->id, 'parcel_id' => 'PARCEL-2']);
        $task = Task::factory()->for($duplicateOne, 'taskable')->create();
        $property = Property::factory()->create(['owner_contact_id' => $duplicateTwo->id]);
        $duplicateOne->properties()->attach($property->id, ['relationship_type' => 'owner']);

        $this->artisan('contacts:merge-surplus-duplicates')
            ->expectsOutputToContain('Preview only')
            ->assertSuccessful();
        $this->assertDatabaseCount('contacts', 3);

        $this->artisan('contacts:merge-surplus-duplicates', ['--execute' => true])
            ->expectsOutputToContain('Merged and soft-archived 2 duplicate Surplus contact(s)')
            ->assertSuccessful();

        $this->assertSame(1, Contact::query()->whereRaw('LOWER(TRIM(first_name)) = ?', ['gerry'])->whereRaw('LOWER(TRIM(last_name)) = ?', ['young'])->count());
        $this->assertSame(3, Contact::withTrashed()->count());
        $this->assertSame($canonical->id, $caseOne->fresh()->claimant_contact_id);
        $this->assertSame($canonical->id, $caseTwo->fresh()->claimant_contact_id);
        $this->assertSame($canonical->id, $task->fresh()->taskable_id);
        $this->assertSame($canonical->id, $property->fresh()->owner_contact_id);
        $this->assertSame('11765 STONEWALL SPRINGS AVE', $canonical->fresh()->mailing_address_line_1);
        $this->assertDatabaseHas('contact_property', ['contact_id' => $canonical->id, 'property_id' => $property->id]);
        $this->assertSoftDeleted('contacts', ['id' => $duplicateOne->id]);
        $this->assertSoftDeleted('contacts', ['id' => $duplicateTwo->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'merged', 'auditable_type' => Contact::class, 'auditable_id' => $canonical->id]);
    }

    public function test_non_surplus_contacts_with_the_same_name_are_not_merged(): void
    {
        Contact::factory()->create(['type' => ContactType::Seller, 'first_name' => 'Gerry', 'last_name' => 'Young']);
        Contact::factory()->create(['type' => ContactType::Seller, 'first_name' => 'Gerry', 'last_name' => 'Young']);

        $this->artisan('contacts:merge-surplus-duplicates', ['--execute' => true])
            ->expectsOutput('No active exact-name duplicate Surplus contacts were found.')
            ->assertSuccessful();

        $this->assertDatabaseCount('contacts', 2);
    }
}
