<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use App\Services\PropertyChecklistService;
use Illuminate\Database\Seeder;

class PropertyChecklistSeeder extends Seeder
{
    public function run(PropertyChecklistService $service): void
    {
        $actor = User::query()->whereIn('role', ['owner', 'admin'])->first();

        Property::query()->select('id')->chunkById(200, function ($properties) use ($service, $actor): void {
            foreach ($properties as $property) {
                $service->initialize($property, $actor);
            }
        });
    }
}
