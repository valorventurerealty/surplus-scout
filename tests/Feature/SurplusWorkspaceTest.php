<?php

namespace Tests\Feature;

use App\Contracts\ToolExecutorInterface;
use App\Enums\SurplusCaseStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\SurplusCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurplusWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_case_and_expected_fee_is_calculated(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $claimant = Contact::factory()->create();

        $this->actingAs($user)->post(route('surplus.store'), $this->validData(['claimant_contact_id' => $claimant->id]))->assertRedirect();

        $case = SurplusCase::query()->sole();
        $this->assertSame('SUR-'.now()->format('Y').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT), $case->case_number);
        $this->assertSame('3000.00', $case->expected_fee);
        $this->assertSame($user->id, $case->created_by);
    }

    public function test_financial_user_sees_surplus_amount_and_live_vvr_fee_pay_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('surplus.create'))->assertOk()
            ->assertSee('Surplus amount')
            ->assertSee('VVR fee percentage')
            ->assertSee('VVR projected fee pay')
            ->assertSee('Surplus amount × fee percentage');
    }

    public function test_claimant_is_required_after_locate_owner_stage(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('surplus.store'), $this->validData(['claimant_contact_id' => null]))
            ->assertSessionHasErrors('claimant_contact_id');
        $this->assertDatabaseCount('surplus_cases', 0);
    }

    public function test_surplus_fee_cannot_exceed_twelve_percent(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('surplus.store'), $this->validData([
            'agreed_fee_percentage' => 12.01,
        ]))->assertSessionHasErrors('agreed_fee_percentage');

        $this->assertDatabaseCount('surplus_cases', 0);
    }

    public function test_actual_fee_cannot_exceed_twelve_percent_of_recovery(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('surplus.store'), $this->validData([
            'recovered_amount' => 10000,
            'actual_fee' => 1200.01,
        ]))->assertSessionHasErrors('actual_fee');

        $this->assertDatabaseCount('surplus_cases', 0);
    }

    public function test_moving_case_to_paid_sets_paid_date_for_financial_recognition(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $case = SurplusCase::factory()->create([
            'status' => SurplusCaseStatus::Approved, 'paid_at' => null,
            'recovered_amount' => 10000, 'actual_fee' => 1200,
        ]);

        $this->actingAs($user)->put(route('surplus.update', $case), $this->validData([
            'status' => SurplusCaseStatus::Paid->value, 'paid_at' => null,
            'recovered_amount' => 10000, 'actual_fee' => 1200,
        ]))->assertRedirect(route('surplus.show', $case));

        $this->assertTrue($case->fresh()->paid_at->isToday());
    }

    public function test_duplicate_foreclosure_case_in_same_jurisdiction_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        SurplusCase::factory()->create(['state' => 'FL', 'county' => 'Putnam', 'foreclosure_case_number' => '2026-CA-100']);

        $this->actingAs($user)->post(route('surplus.store'), $this->validData(['state' => 'FL', 'county' => 'Putnam', 'foreclosure_case_number' => '2026-CA-100']))
            ->assertSessionHasErrors('foreclosure_case_number');
    }

    public function test_marketing_cannot_access_surplus_or_its_tasks(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $case = SurplusCase::factory()->create();
        $task = Task::factory()->for($case, 'taskable')->create();

        $this->actingAs($marketing)->get(route('surplus.index'))->assertForbidden();
        $this->actingAs($marketing)->get(route('surplus.show', $case))->assertForbidden();
        $this->actingAs($marketing)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($marketing)->get(route('tasks.index'))->assertOk()->assertDontSee($task->title);
    }

    public function test_read_only_user_sees_case_without_financials_or_documents(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $case = SurplusCase::factory()->create(['surplus_amount' => 91234.56, 'document_drive_url' => 'https://drive.google.com/drive/folders/private']);

        $this->actingAs($user)->get(route('surplus.show', $case))->assertOk()
            ->assertDontSee('91,234.56')->assertDontSee('drive.google.com');
        $this->actingAs($user)->get(route('surplus.edit', $case))->assertForbidden();
    }

    public function test_task_can_be_linked_to_surplus_case(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $case = SurplusCase::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Confirm claimant identity', 'status' => 'pending', 'priority' => 'high', 'subject' => 'surplus:'.$case->id, 'recurrence_interval' => 1])->assertRedirect();

        $this->assertTrue(Task::query()->sole()->taskable->is($case));
    }

    public function test_manager_can_link_multiple_people_to_a_surplus_case(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $claimant = Contact::factory()->create(['first_name' => 'Daniel', 'last_name' => 'Benson']);
        $relative = Contact::factory()->create(['first_name' => 'Jamie', 'last_name' => 'Benson']);
        $case = SurplusCase::factory()->create(['claimant_contact_id' => $claimant->id]);

        $this->actingAs($user)->post(route('surplus.contacts.store', $case), [
            'contact_id' => $relative->id, 'role' => 'relative',
            'relationship_notes' => "Owner's daughter; mailer candidate.",
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_surplus_case', [
            'surplus_case_id' => $case->id, 'contact_id' => $relative->id,
            'role' => 'relative', 'relationship_notes' => "Owner's daughter; mailer candidate.",
        ]);
        $this->actingAs($user)->get(route('surplus.show', $case))->assertOk()
            ->assertSee('Associated people')->assertSee('Jamie Benson')->assertSee('Mailer candidate');
    }

    public function test_primary_claimant_cannot_be_unlinked_from_case_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $claimant = Contact::factory()->create();
        $case = app(\App\Services\SurplusCaseService::class)->create($this->validData([
            'claimant_contact_id' => $claimant->id,
        ]), $user);
        $association = $case->contacts()->whereKey($claimant->id)->firstOrFail()->pivot->id;

        $this->actingAs($user)->delete(route('surplus.contacts.destroy', [$case, $association]))
            ->assertSessionHasErrors('contact');
        $this->assertDatabaseHas('contact_surplus_case', ['id' => $association]);
    }

    public function test_read_only_user_cannot_link_or_unlink_surplus_contacts(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $case = SurplusCase::factory()->create();
        $contact = Contact::factory()->create();

        $this->actingAs($user)->post(route('surplus.contacts.store', $case), [
            'contact_id' => $contact->id, 'role' => 'relative',
        ])->assertForbidden();
    }

    public function test_duplicate_surplus_case_cleanup_moves_relative_links_to_original_case(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $claimant = Contact::factory()->create();
        $relative = Contact::factory()->create();
        $original = SurplusCase::factory()->create([
            'claimant_contact_id' => $claimant->id, 'state' => 'FL', 'county' => 'Osceola County',
            'parcel_id' => '11-25-28-3700-0022-0050', 'surplus_amount' => 100,
        ]);
        $duplicate = SurplusCase::factory()->create([
            'claimant_contact_id' => $claimant->id, 'state' => 'FL', 'county' => 'Osceola',
            'parcel_id' => '112528370000220050', 'surplus_amount' => 68973.87,
            'agreed_fee_percentage' => 12, 'expected_fee' => 8276.86,
        ]);
        $duplicate->contacts()->attach($relative->id, [
            'role' => 'relative', 'relationship_notes' => 'Spouse', 'created_by' => $user->id,
        ]);

        $this->artisan('surplus:merge-duplicate-cases')->assertSuccessful();
        $this->assertDatabaseCount('surplus_cases', 2);
        $this->artisan('surplus:merge-duplicate-cases', ['--execute' => true])->assertSuccessful();

        $this->assertSame(1, SurplusCase::query()->count());
        $this->assertSoftDeleted('surplus_cases', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('contact_surplus_case', [
            'surplus_case_id' => $original->id, 'contact_id' => $relative->id,
            'role' => 'relative', 'relationship_notes' => 'Spouse',
        ]);
    }

    public function test_status_sort_uses_business_pipeline_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        SurplusCase::factory()->create(['case_number' => 'SUR-CLOSED', 'status' => SurplusCaseStatus::Closed]);
        SurplusCase::factory()->create(['case_number' => 'SUR-RESEARCH', 'status' => SurplusCaseStatus::Research]);
        SurplusCase::factory()->create(['case_number' => 'SUR-MAILER', 'status' => SurplusCaseStatus::MailerSent]);
        SurplusCase::factory()->create(['case_number' => 'SUR-CONTACT', 'status' => SurplusCaseStatus::Contact]);
        SurplusCase::factory()->create(['case_number' => 'SUR-PAID', 'status' => SurplusCaseStatus::Paid]);

        $this->actingAs($user)->get(route('surplus.index', ['sort' => 'status', 'direction' => 'asc']))
            ->assertSeeInOrder(['SUR-RESEARCH', 'SUR-MAILER', 'SUR-CONTACT', 'SUR-PAID', 'SUR-CLOSED']);
    }

    public function test_mailer_sent_is_available_as_a_surplus_pipeline_stage(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('surplus.create'))->assertOk()
            ->assertSee('Mailer Sent')
            ->assertSee('value="mailer_sent"', false);

        $this->actingAs($user)->post(route('surplus.store'), $this->validData([
            'status' => SurplusCaseStatus::MailerSent->value,
        ]))->assertRedirect();

        $this->assertSame(SurplusCaseStatus::MailerSent, SurplusCase::query()->sole()->status);
    }

    public function test_ai_surplus_tools_enforce_permissions_and_recalculate_fee(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $case = SurplusCase::factory()->create(['status' => SurplusCaseStatus::Contact, 'surplus_amount' => 20000, 'agreed_fee_percentage' => 10, 'expected_fee' => 2000]);
        $executor = app(ToolExecutorInterface::class);

        $read = $executor->execute('get_surplus_case', ['surplus_case_id' => $case->id], $owner);
        $this->assertSame($case->id, $read['record']['id']);
        $executor->execute('update_surplus_case', ['surplus_case_id' => $case->id, 'changes' => ['surplus_amount' => 30000, 'agreed_fee_percentage' => 12]], $owner);
        $this->assertSame('3600.00', $case->refresh()->expected_fee);

        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $executor->execute('get_surplus_case', ['surplus_case_id' => $case->id], $marketing);
    }

    public function test_ai_rejects_surplus_fee_above_twelve_percent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $case = SurplusCase::factory()->create(['status' => SurplusCaseStatus::Contact]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ToolExecutorInterface::class)->execute('update_surplus_case', [
            'surplus_case_id' => $case->id,
            'changes' => ['agreed_fee_percentage' => 25],
        ], $owner);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'status' => SurplusCaseStatus::Contact->value,
            'claimant_contact_id' => Contact::factory()->create()->id,
            'state' => 'FL', 'county' => 'Putnam', 'parcel_id' => '31-12-27-7227-0011-0120',
            'foreclosure_case_number' => '2026-CA-500', 'surplus_amount' => 25000,
            'agreed_fee_percentage' => 12, 'sale_date' => now()->subMonth()->toDateString(),
            'claim_deadline' => now()->addMonths(6)->toDateString(), 'notes' => 'Initial recovery review.',
        ], $overrides);
    }
}
