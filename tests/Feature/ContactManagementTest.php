<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_contacts(): void
    {
        $this->get(route('contacts.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_create_contact_and_audit_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);

        $response = $this->actingAs($user)->post(route('contacts.store'), [
            'first_name' => 'Jordan', 'last_name' => 'Lee', 'email' => 'jordan@example.com',
            'type' => ContactType::Seller->value, 'status' => ContactStatus::New->value,
        ]);

        $contact = Contact::query()->where('email', 'jordan@example.com')->firstOrFail();
        $response->assertRedirect(route('contacts.show', $contact));
        $this->assertSame($user->id, $contact->created_by);
        $this->assertSame('jordan@example.com', $contact->normalized_email);
        $this->assertDatabaseHas(AuditLog::class, ['event' => 'created', 'auditable_id' => $contact->id]);
    }

    public function test_read_only_user_can_view_but_cannot_mutate_contacts(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $contact = Contact::factory()->create();

        $this->actingAs($user)->get(route('contacts.show', $contact))->assertOk();
        $this->actingAs($user)->get(route('contacts.edit', $contact))->assertForbidden();
        $this->actingAs($user)->delete(route('contacts.destroy', $contact))->assertForbidden();
    }

    public function test_contact_input_is_validated(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($user)->post(route('contacts.store'), ['first_name' => ''])
            ->assertSessionHasErrors(['first_name', 'last_name', 'type', 'status']);
    }

    public function test_follow_up_date_can_include_a_purpose(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $contact = Contact::factory()->create([
            'first_name' => 'Follow',
            'last_name' => 'Up',
            'type' => ContactType::Seller,
            'status' => ContactStatus::Active,
            'next_follow_up_at' => null,
            'next_follow_up_purpose' => null,
        ]);

        $this->actingAs($user)->put(route('contacts.update', $contact), [
            'first_name' => 'Follow',
            'last_name' => 'Up',
            'type' => ContactType::Seller->value,
            'status' => ContactStatus::Active->value,
            'next_follow_up_at' => '2026-09-25 10:30:00',
            'next_follow_up_purpose' => 'Discuss the revised purchase offer',
        ])->assertRedirect(route('contacts.show', $contact));

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'next_follow_up_purpose' => 'Discuss the revised purchase offer',
        ]);
        $this->actingAs($user)->get(route('contacts.index'))
            ->assertOk()
            ->assertSee('Discuss the revised purchase offer');
        $this->actingAs($user)->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('Follow-up purpose')
            ->assertSee('Discuss the revised purchase offer');
    }

    public function test_follow_up_purpose_requires_a_follow_up_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('contacts.store'), [
            'first_name' => 'Missing',
            'last_name' => 'Date',
            'type' => ContactType::Seller->value,
            'status' => ContactStatus::New->value,
            'next_follow_up_purpose' => 'Call about the property',
        ])->assertSessionHasErrors('next_follow_up_at');
    }

    public function test_contact_can_be_searched(): void
    {
        $user = User::factory()->create();
        Contact::factory()->create(['first_name' => 'UniqueNeedle']);
        Contact::factory()->create(['first_name' => 'SomeoneElse']);

        $this->actingAs($user)->get(route('contacts.index', ['search' => 'UniqueNeedle']))
            ->assertOk()->assertSee('UniqueNeedle')->assertDontSee('SomeoneElse');
    }

    public function test_user_can_choose_more_than_twenty_contacts_per_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        Contact::factory()->count(60)->create();

        $response = $this->actingAs($user)->get(route('contacts.index', ['per_page' => 50]));

        $response->assertOk()->assertSee('50 per page');
        $this->assertSame(50, $response->viewData('contacts')->perPage());
        $this->assertCount(50, $response->viewData('contacts')->items());
    }

    public function test_user_can_export_selected_contacts_as_safe_csv(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $first = Contact::factory()->create(['first_name' => 'Export', 'last_name' => 'One', 'company' => '=UNSAFE']);
        $second = Contact::factory()->create(['first_name' => 'Export', 'last_name' => 'Two']);
        Contact::factory()->create(['first_name' => 'DoNot', 'last_name' => 'Export']);

        $response = $this->actingAs($user)->post(route('contacts.export'), [
            'mode' => 'selected', 'contact_ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Export,One', $csv);
        $this->assertStringContainsString("'=UNSAFE", $csv);
        $this->assertStringContainsString('Export,Two', $csv);
        $this->assertStringNotContainsString('DoNot,Export', $csv);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'event' => 'exported']);
    }

    public function test_contact_export_respects_surplus_visibility(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        Contact::factory()->create(['first_name' => 'Visible', 'last_name' => 'Seller', 'type' => ContactType::Seller]);
        Contact::factory()->create(['first_name' => 'Private', 'last_name' => 'Claimant', 'type' => ContactType::Surplus]);

        $response = $this->actingAs($marketing)->post(route('contacts.export'), ['mode' => 'filtered']);
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Visible,Seller', $csv);
        $this->assertStringNotContainsString('Private,Claimant', $csv);
    }

    public function test_surplus_contact_type_can_be_created_and_filtered(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $this->actingAs($user)->post(route('contacts.store'), [
            'first_name' => 'Morgan', 'last_name' => 'Claimant',
            'type' => ContactType::Surplus->value, 'status' => ContactStatus::Active->value,
        ])->assertRedirect();

        $contact = Contact::query()->sole();
        $this->assertSame(ContactType::Surplus, $contact->type);
        $this->actingAs($user)->get(route('contacts.index', ['type' => ContactType::Surplus->value]))
            ->assertOk()->assertSee('Morgan')->assertSee('Surplus');
    }

    public function test_realtor_contact_can_be_assigned_to_multiple_properties_transactionally(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $firstProperty = Property::factory()->create(['address' => '10 Realtor Way']);
        $secondProperty = Property::factory()->create(['address' => '20 Listing Lane']);

        $response = $this->actingAs($user)->post(route('contacts.store'), [
            'first_name' => 'Riley',
            'last_name' => 'Realtor',
            'company' => 'VVR Realty Partners',
            'email' => 'riley.realtor@example.com',
            'type' => ContactType::Realtor->value,
            'status' => ContactStatus::Active->value,
            'property_assignments_present' => 1,
            'property_ids' => [$firstProperty->id, $secondProperty->id],
        ]);

        $contact = Contact::query()->where('email', 'riley.realtor@example.com')->firstOrFail();
        $response->assertRedirect(route('contacts.show', $contact));
        $this->assertSame(ContactType::Realtor, $contact->type);
        $this->assertDatabaseHas('contact_property', [
            'contact_id' => $contact->id,
            'property_id' => $firstProperty->id,
            'relationship_type' => 'associated',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('contact_property', [
            'contact_id' => $contact->id,
            'property_id' => $secondProperty->id,
        ]);

        $this->actingAs($user)->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('10 Realtor Way')
            ->assertSee('20 Listing Lane');
        $this->actingAs($user)->get(route('properties.show', $firstProperty))
            ->assertOk()
            ->assertSee('Riley Realtor');

        $this->actingAs($user)->put(route('contacts.update', $contact), [
            'first_name' => 'Riley',
            'last_name' => 'Realtor',
            'company' => 'VVR Realty Partners',
            'email' => 'riley.realtor@example.com',
            'type' => ContactType::Realtor->value,
            'status' => ContactStatus::Active->value,
            'property_assignments_present' => 1,
            'property_ids' => [$secondProperty->id],
        ])->assertRedirect(route('contacts.show', $contact));

        $this->assertDatabaseMissing('contact_property', [
            'contact_id' => $contact->id,
            'property_id' => $firstProperty->id,
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'property_assignments_updated',
            'auditable_type' => $contact->getMorphClass(),
            'auditable_id' => $contact->id,
        ]);
    }

    public function test_archived_property_cannot_be_assigned_to_contact(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $property = Property::factory()->create();
        $property->delete();

        $this->actingAs($user)->post(route('contacts.store'), [
            'first_name' => 'Taylor',
            'last_name' => 'Agent',
            'type' => ContactType::Realtor->value,
            'status' => ContactStatus::Active->value,
            'property_assignments_present' => 1,
            'property_ids' => [$property->id],
        ])->assertSessionHasErrors('property_ids.0');

        $this->assertDatabaseMissing('contacts', ['first_name' => 'Taylor', 'last_name' => 'Agent']);
        $this->assertDatabaseCount('contact_property', 0);
    }
}
