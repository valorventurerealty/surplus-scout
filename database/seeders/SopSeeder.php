<?php

namespace Database\Seeders;

use App\Models\Sop;
use Illuminate\Database\Seeder;

class SopSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || Sop::query()->exists()) { return; }
        Sop::factory()->count(5)->create();
    }
}
