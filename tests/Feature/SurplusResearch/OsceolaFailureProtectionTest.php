<?php

namespace Tests\Feature\SurplusResearch;

use App\Contracts\SurplusResearch\CountySurplusSourceInterface;
use App\Data\SurplusResearch\DownloadedCountyReport;
use App\Enums\SurplusResearchRunStatus;
use App\Enums\UserRole;
use App\Models\SurplusCase;
use App\Models\SurplusResearchRun;
use App\Models\User;
use App\Services\SurplusResearch\Osceola\OsceolaClerkSource;
use App\Services\SurplusResearch\Osceola\OsceolaPdfParser;
use App\Services\SurplusResearch\ResearchRunService;
use App\Services\SurplusResearch\SurplusImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OsceolaFailureProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_failure_is_reported_without_importing(): void
    {
        Http::fake(['*' => Http::response('Unavailable', 503)]);
        $this->expectException(RuntimeException::class);
        (new OsceolaClerkSource)->download();
    }

    public function test_parser_failure_never_marks_existing_records_no_longer_listed(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);
        $case = SurplusCase::factory()->create([
            'claimant_contact_id' => null, 'property_id' => null, 'assigned_user_id' => null,
            'source_name' => 'Osceola County Clerk', 'county' => 'Osceola',
            'surplus_availability' => 'available', 'clerk_unique_key' => 'OSCEOLA|SAFE123456789|1-2026',
        ]);
        $run = SurplusResearchRun::query()->create([
            'token' => fake()->uuid(), 'county' => 'Osceola', 'source_name' => 'Osceola County Clerk',
            'source_url' => config('surplus_research.osceola.source_url'),
            'status' => SurplusResearchRunStatus::Queued, 'triggered_by' => $actor->id,
        ]);
        $source = new class implements CountySurplusSourceInterface {
            public function download(): DownloadedCountyReport { return new DownloadedCountyReport('%PDF-invalid', config('surplus_research.osceola.source_url'), str_repeat('a', 64), 100); }
        };
        $parser = Mockery::mock(OsceolaPdfParser::class);
        $parser->shouldReceive('parse')->once()->andThrow(new RuntimeException('Unexpected document received.'));
        $importer = Mockery::mock(SurplusImportService::class);
        $importer->shouldNotReceive('import');
        $service = new ResearchRunService($source, $parser, $importer);

        try { $service->execute($run); } catch (RuntimeException) {}

        $this->assertSame('available', $case->fresh()->surplus_availability);
        $this->assertSame(1, SurplusCase::query()->count());
    }
}
