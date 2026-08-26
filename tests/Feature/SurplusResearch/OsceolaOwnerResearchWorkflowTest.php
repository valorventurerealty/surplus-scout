<?php

namespace Tests\Feature\SurplusResearch;

use App\Contracts\SurplusResearch\CountyOwnerResearchProviderInterface;
use App\Data\SurplusResearch\PropertyAppraiserRecordData;
use App\Data\SurplusResearch\TrimNoticeData;
use App\Enums\SurplusOwnerResearchStatus;
use App\Enums\UserRole;
use App\Exceptions\OwnerResearchException;
use App\Models\SurplusCase;
use App\Models\SurplusOwnerResearchBatch;
use App\Models\User;
use App\Services\SurplusResearch\SurplusOwnerResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OsceolaOwnerResearchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_2025_historical_owner_updates_the_same_case_and_never_creates_a_duplicate(): void
    {
        Storage::fake('local');
        [$case, $batch, $actor] = $this->records();
        $this->app->bind(CountyOwnerResearchProviderInterface::class, fn () => new FakeOwnerProvider(
            current: 'ABC HOLDINGS LLC',
            trims: [2025 => $this->trim(2025, 'JOHN A SMITH')],
        ));

        app(SurplusOwnerResearchService::class)->research($case, $batch, $actor);

        $this->assertSame(1, SurplusCase::query()->count());
        $case->refresh();
        $this->assertSame('JOHN A SMITH', $case->previous_owner_raw);
        $this->assertSame(2025, $case->historical_trim_year);
        $this->assertSame(SurplusOwnerResearchStatus::ReadyForSkipTrace->value, $case->research_status);
        $this->assertTrue($case->property_appraiser_verified);
        $this->assertTrue($case->historical_owner_verified);
        $this->assertDatabaseCount('surplus_owner_research_attempts', 1);
        $this->assertDatabaseHas('surplus_owner_research_events', ['event' => 'Case Updated']);
    }

    public function test_matching_2025_owner_uses_different_2024_owner(): void
    {
        Storage::fake('local');
        [$case, $batch, $actor] = $this->records();
        $this->app->bind(CountyOwnerResearchProviderInterface::class, fn () => new FakeOwnerProvider(
            current: 'JANE SMITH',
            trims: [2025 => $this->trim(2025, 'JANE SMITH'), 2024 => $this->trim(2024, 'MARY JONES')],
        ));
        app(SurplusOwnerResearchService::class)->research($case, $batch, $actor);
        $this->assertSame('MARY JONES', $case->fresh()->previous_owner_raw);
        $this->assertSame(2024, $case->fresh()->historical_trim_year);
    }

    public function test_both_years_matching_routes_existing_case_to_unresolved_review(): void
    {
        Storage::fake('local');
        [$case, $batch, $actor] = $this->records();
        $this->app->bind(CountyOwnerResearchProviderInterface::class, fn () => new FakeOwnerProvider(
            current: 'JANE SMITH',
            trims: [2025 => $this->trim(2025, 'JANE SMITH'), 2024 => $this->trim(2024, 'JANE SMITH')],
        ));
        app(SurplusOwnerResearchService::class)->research($case, $batch, $actor);
        $case->refresh();
        $this->assertSame(SurplusOwnerResearchStatus::OwnerMatchUnresolved->value, $case->research_status);
        $this->assertNull($case->previous_owner_raw);
        $this->assertSame(1, SurplusCase::query()->count());
    }

    public function test_missing_2025_uses_available_2024_with_warning(): void
    {
        Storage::fake('local');
        [$case, $batch, $actor] = $this->records();
        $this->app->bind(CountyOwnerResearchProviderInterface::class, fn () => new FakeOwnerProvider(
            current: 'CURRENT OWNER', trims: [2024 => $this->trim(2024, 'PRIOR OWNER')],
        ));
        app(SurplusOwnerResearchService::class)->research($case, $batch, $actor);
        $this->assertSame(2024, $case->fresh()->historical_trim_year);
        $this->assertStringContainsString('2025 TRIM notice was unavailable', $case->fresh()->owner_research_notes);
    }

    public function test_property_appraiser_failure_preserves_existing_research_fields_and_case_count(): void
    {
        Storage::fake('local');
        [$case, $batch, $actor] = $this->records();
        $case->update(['previous_owner_raw' => 'PRESERVED OWNER', 'claimant_mailing_address' => 'PRESERVED ADDRESS']);
        $this->app->bind(CountyOwnerResearchProviderInterface::class, fn () => new FakeOwnerProvider(
            current: '', trims: [], error: new OwnerResearchException(
                'Property Appraiser unavailable.', SurplusOwnerResearchStatus::PropertyAppraiserError, true,
            ),
        ));

        try { app(SurplusOwnerResearchService::class)->research($case, $batch, $actor); } catch (OwnerResearchException) {}

        $case->refresh();
        $this->assertSame('PRESERVED OWNER', $case->previous_owner_raw);
        $this->assertSame('PRESERVED ADDRESS', $case->claimant_mailing_address);
        $this->assertSame(SurplusOwnerResearchStatus::PropertyAppraiserError->value, $case->research_status);
        $this->assertSame(1, SurplusCase::query()->count());
        $this->assertDatabaseCount('surplus_owner_research_attempts', 1);
    }

    private function records(): array
    {
        $actor = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);
        $case = SurplusCase::factory()->create([
            'claimant_contact_id' => null, 'property_id' => null, 'assigned_user_id' => null,
            'source_name' => 'Osceola County Clerk', 'county' => 'Osceola',
            'parcel_id' => '36-27-31-6000-000L-1620', 'normalized_parcel_id' => '3627316000000L1620',
            'research_status' => SurplusOwnerResearchStatus::Pending->value,
        ]);
        $batch = SurplusOwnerResearchBatch::query()->create([
            'token' => fake()->uuid(), 'county' => 'Osceola', 'mode' => 'selected', 'status' => 'queued',
            'total_cases' => 1, 'case_ids' => [$case->id], 'triggered_by' => $actor->id,
        ]);
        return [$case, $batch, $actor];
    }

    private function trim(int $year, string $owner): TrimNoticeData
    {
        return new TrimNoticeData($year, $owner, null, '123 MAIN ST', 'Orlando', 'FL', '32801',
            'https://search.property-appraiser.org/Search/GetAttachment/1', str_repeat('a', 64), '%PDF-test', 'TRIM text');
    }
}

class FakeOwnerProvider implements CountyOwnerResearchProviderInterface
{
    public function __construct(private string $current, private array $trims, private ?OwnerResearchException $error = null) {}
    public function findProperty(string $parcelId): PropertyAppraiserRecordData
    {
        if ($this->error) throw $this->error;
        return new PropertyAppraiserRecordData('3627316000000L1620', '3627316000000L1620', $this->current, '0 TEST RD', 'https://search.property-appraiser.org/Search/MainSearch?pin=3627316000000L1620');
    }
    public function findTrimNotice(string $parcelId, int $year): ?TrimNoticeData { return $this->trims[$year] ?? null; }
}
