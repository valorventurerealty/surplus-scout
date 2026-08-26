<?php

namespace Tests\Unit\SurplusResearch;

use App\Jobs\ResearchOsceolaSurplusOwnerJob;
use App\Jobs\RunOsceolaSurplusResearchJob;
use PHPUnit\Framework\TestCase;

class SurplusResearchQueueIsolationTest extends TestCase
{
    public function test_clerk_report_job_uses_the_dedicated_surplus_queue(): void
    {
        $job = new RunOsceolaSurplusResearchJob(1);

        $this->assertSame('surplus-research', $job->queue);
        $this->assertSame(300, $job->timeout);
    }

    public function test_owner_research_job_uses_the_dedicated_surplus_queue(): void
    {
        $this->assertSame('surplus-research', (new ResearchOsceolaSurplusOwnerJob(1, 1, 1))->queue);
    }
}
