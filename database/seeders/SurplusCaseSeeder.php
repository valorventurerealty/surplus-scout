<?php

namespace Database\Seeders;

use App\Models\SurplusCase;
use Illuminate\Database\Seeder;

class SurplusCaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || SurplusCase::query()->exists()) {
            return;
        }

        SurplusCase::factory()->count(4)->create();
    }
}
