<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Enums\DealType;
use App\Enums\SopDepartment;
use App\Enums\SopStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Sop;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreTaxAuctionClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_tax_auction_options_are_available_in_applicable_workspaces(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get(route('sops.create'))->assertOk()->assertSee('PreTax Auctions');
        $this->actingAs($owner)->get(route('armory.create'))->assertOk()->assertSee('PreTax Auctions');
        $this->actingAs($owner)->get(route('armory.email-templates.create'))->assertOk()->assertSee('PreTax Auction Outreach');
        $this->actingAs($owner)->get(route('contacts.create'))->assertOk()->assertSee('PreTax Auctions');
        $this->actingAs($owner)->get(route('deals.create'))->assertOk()->assertSee('PreTax Auction Acquisition');

        $this->actingAs($owner)->post(route('sops.store'), [
            'title' => 'PreTax Auction Owner Outreach',
            'department' => SopDepartment::PreTaxAuctions->value,
            'status' => SopStatus::Active->value,
            'version_label' => '1.0',
            'content_text' => 'Verify the auction date before beginning owner outreach.',
        ])->assertRedirect();

        $this->assertSame(SopDepartment::PreTaxAuctions, Sop::query()->sole()->department);
    }

    public function test_pre_tax_auction_contacts_and_deals_respect_department_permissions(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $contact = Contact::factory()->create([
            'first_name' => 'PrivatePreTax',
            'last_name' => 'Owner',
            'type' => ContactType::PreTaxAuctions,
            'status' => ContactStatus::Active,
        ]);
        $deal = Deal::factory()->create([
            'title' => 'Private PreTax Acquisition',
            'type' => DealType::PreTaxAuctionAcquisition,
        ]);
        $contactTask = Task::factory()->create(['title' => 'Private PreTax contact task', 'taskable_type' => $contact->getMorphClass(), 'taskable_id' => $contact->id]);
        $dealTask = Task::factory()->create(['title' => 'Private PreTax deal task', 'taskable_type' => $deal->getMorphClass(), 'taskable_id' => $deal->id]);

        $this->actingAs($owner)->get(route('contacts.show', $contact))->assertOk();
        $this->actingAs($owner)->get(route('deals.show', $deal))->assertOk();

        $this->actingAs($marketing)->get(route('contacts.index'))->assertOk()->assertDontSee('PrivatePreTax');
        $this->actingAs($marketing)->get(route('contacts.show', $contact))->assertForbidden();
        $this->actingAs($marketing)->get(route('deals.index'))->assertOk()->assertDontSee('Private PreTax Acquisition');
        $this->actingAs($marketing)->get(route('deals.show', $deal))->assertForbidden();
        $this->actingAs($marketing)->get(route('contacts.create'))->assertOk()->assertDontSee('PreTax Auctions');
        $this->actingAs($marketing)->get(route('tasks.index'))->assertOk()
            ->assertDontSee('Private PreTax contact task')
            ->assertDontSee('Private PreTax deal task');
        $this->actingAs($marketing)->get(route('tasks.show', $contactTask))->assertForbidden();
        $this->actingAs($marketing)->get(route('tasks.show', $dealTask))->assertForbidden();
    }
}
