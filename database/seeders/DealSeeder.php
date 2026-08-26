<?php

namespace Database\Seeders;

use App\Models\Deal;
use Illuminate\Database\Seeder;

class DealSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || Deal::query()->exists()) {
            return;
        }

        Deal::factory()->count(4)->create();
    }
}
