<?php

namespace Database\Seeders;

use App\Models\PreAuctionAcquisition;
use Illuminate\Database\Seeder;

class PreAuctionAcquisitionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || PreAuctionAcquisition::query()->exists()) return;
        PreAuctionAcquisition::factory()->count(4)->create();
    }
}
