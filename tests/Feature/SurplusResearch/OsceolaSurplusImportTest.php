<?php

namespace Tests\Feature\SurplusResearch;

use App\Data\SurplusResearch\CountySurplusRecordData;
use App\Data\SurplusResearch\CountySurplusReportData;
use App\Enums\SurplusCaseStatus;
use App\Enums\SurplusResearchRunStatus;
use App\Enums\UserRole;
use App\Models\SurplusCase;
use App\Models\SurplusResearchRun;
use App\Models\User;
use App\Services\SurplusCaseService;
use App\Services\SurplusResearch\SurplusDuplicateService;
use App\Services\SurplusResearch\SurplusImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OsceolaSurplusImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_existing_changed_and_removed_records_are_handled_idempotently(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);
        $run = $this->createResearchRun($actor);
        $old = SurplusCase::factory()->create([
            'claimant_contact_id' => null, 'property_id' => null, 'assigned_user_id' => null,
            'source_name' => 'Osceola County Clerk', 'county' => 'Osceola', 'state' => 'FL',
            'parcel_id' => 'OLD123456789', 'normalized_parcel_id' => 'OLD123456789',
            'tax_deed_number' => '1-2025', 'clerk_unique_key' => 'OSCEOLA|OLD123456789|1-2025',
            'surplus_availability' => 'available',
        ]);
        $report = $this->report('8121.74');
        $service = new SurplusImportService(new SurplusDuplicateService, app(SurplusCaseService::class));

        $first = $service->import($report, $run, $actor);
        $this->assertSame(1, $first['new_records']);
        $this->assertSame(1, $first['removed_records']);
        $this->assertSame('no_longer_listed', $old->fresh()->surplus_availability);
        $created = SurplusCase::query()->where('clerk_unique_key', 'OSCEOLA|3627316000000L1620|69-2026')->firstOrFail();
        $this->assertSame('pending_owner_research', $created->research_status);
        $this->assertSame(SurplusCaseStatus::Research, $created->status);

        $secondRun = $this->createResearchRun($actor);
        $second = $service->import($report, $secondRun, $actor);
        $this->assertSame(0, $second['new_records']);
        $this->assertSame(1, $second['existing_records']);
        $this->assertSame(1, SurplusCase::query()->where('clerk_unique_key', $created->clerk_unique_key)->count());

        $thirdRun = $this->createResearchRun($actor);
        $changed = $service->import($this->report('9000.00'), $thirdRun, $actor);
        $this->assertSame(1, $changed['amount_changes']);
        $this->assertDatabaseHas('surplus_amount_histories', [
            'surplus_case_id' => $created->id, 'research_run_id' => $thirdRun->id,
            'previous_amount' => 8121.74, 'new_amount' => 9000.00,
        ]);
    }

    public function test_read_only_user_cannot_start_a_run(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly, 'is_active' => true]);
        $this->actingAs($user)->post(route('surplus-scout.osceola.runs.store'))->assertForbidden();
    }

    public function test_a_current_report_restores_a_matching_soft_deleted_case(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);
        $run = $this->createResearchRun($actor);
        $case = SurplusCase::factory()->create([
            'claimant_contact_id' => null, 'property_id' => null, 'assigned_user_id' => null,
            'source_name' => 'Osceola County Clerk', 'county' => 'Osceola', 'state' => 'FL',
            'parcel_id' => '3627316000000L1620', 'normalized_parcel_id' => '3627316000000L1620',
            'tax_deed_number' => '69-2026', 'clerk_unique_key' => 'OSCEOLA|3627316000000L1620|69-2026',
            'surplus_availability' => 'available', 'surplus_amount' => 8121.74,
        ]);
        $case->delete();

        $result = app(SurplusImportService::class)->import($this->report('8121.74'), $run, $actor);

        $this->assertSame(0, $result['new_records']);
        $this->assertSame(1, $result['existing_records']);
        $this->assertFalse($case->fresh()->trashed());
        $this->assertSame(1, SurplusCase::withTrashed()->where('clerk_unique_key', $case->clerk_unique_key)->count());
    }

    public function test_an_authorized_user_cannot_queue_two_active_runs(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);
        $this->actingAs($user)->post(route('surplus-scout.osceola.runs.store'))->assertRedirect();
        $this->actingAs($user)->post(route('surplus-scout.osceola.runs.store'))
            ->assertSessionHasErrors('research');
        $this->assertSame(1, SurplusResearchRun::query()->count());
    }

    private function createResearchRun(User $actor): SurplusResearchRun
    {
        return SurplusResearchRun::query()->create([
            'token' => fake()->uuid(), 'county' => 'Osceola', 'source_name' => 'Osceola County Clerk',
            'source_url' => 'https://courts.osceolaclerk.com/reports/TaxDeedsSurplusFundsAvailableWeb.pdf',
            'status' => SurplusResearchRunStatus::Running, 'triggered_by' => $actor->id,
        ]);
    }

    private function report(string $amount): CountySurplusReportData
    {
        $record = new CountySurplusRecordData(
            'Osceola', 'FL', '36-27-31-6000-000L-1620', '3627316000000L1620', '69-2026', '61362024',
            CarbonImmutable::parse('2026-08-20'), $amount, 'OSCEOLA|3627316000000L1620|69-2026', [],
        );
        return new CountySurplusReportData(CarbonImmutable::parse('2026-08-24'), [$record], [], 0);
    }
}
