<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InitialAdminSeeder::class,
            DevelopmentDataSeeder::class,
            TaskSeeder::class,
            TaskTemplateSeeder::class,
            PropertySeeder::class,
            PropertyChecklistSeeder::class,
            CalendarEventSeeder::class,
            DealSeeder::class,
            SurplusCaseSeeder::class,
            PreAuctionAcquisitionSeeder::class,
            SopSeeder::class,
            ProjectionScenarioSeeder::class,
        ]);
    }
}
