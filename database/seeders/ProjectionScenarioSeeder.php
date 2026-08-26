<?php

namespace Database\Seeders;

use App\Enums\ProjectionScenarioStatus;
use App\Enums\UserRole;
use App\Models\ProjectionScenario;
use App\Models\User;
use App\Services\ProjectionScenarioService;
use Illuminate\Database\Seeder;

class ProjectionScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $name = 'VVR Full Projections 2026–2030';
        if (ProjectionScenario::withTrashed()->where('name', $name)->exists()) {
            $this->command?->info('The VVR full projection scenario already exists; no planning data was overwritten.');

            return;
        }
        $owner = User::query()->where('role', UserRole::Owner)->where('is_active', true)->first()
            ?? User::query()->where('role', UserRole::Admin)->where('is_active', true)->first();
        if (! $owner) {
            $this->command?->warn('Projection scenario not seeded because no active Owner or Admin exists.');

            return;
        }

        $monthly = [
            'land_flip' => [
                2026 => [0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 1, 0],
                2027 => [0, 0, 1, 0, 1, 0, 1, 0, 2, 0, 1, 2],
                2028 => [1, 1, 1, 2, 1, 1, 1, 2, 1, 1, 1, 2],
                2029 => [1, 2, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2],
                2030 => [2, 2, 2, 2, 2, 3, 3, 2, 3, 2, 3, 4],
            ],
            'property_flip' => [
                2026 => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
                2027 => [0, 0, 1, 0, 0, 1, 0, 0, 1, 0, 0, 1],
                2028 => [0, 0, 1, 1, 0, 1, 1, 0, 1, 0, 1, 1],
                2029 => [1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1],
                2030 => [1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 1, 1],
            ],
            'rental' => [
                2026 => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
                2027 => [1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 3],
                2028 => [3, 3, 3, 4, 4, 4, 5, 5, 5, 6, 6, 7],
                2029 => [7, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 13],
                2030 => [13, 14, 14, 15, 15, 16, 16, 17, 17, 18, 19, 20],
            ],
            'surplus' => [
                2026 => [0, 0, 0, 0, 0, 1, 0, 1, 0, 1, 1, 1],
                2027 => [0, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1],
                2028 => [1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2],
                2029 => [2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3],
                2030 => [3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3],
            ],
        ];
        $entries = [];
        foreach ($monthly as $category => $years) {
            foreach ($years as $year => $months) {
                foreach ($months as $index => $units) {
                    $entries[$category][$year][$index + 1] = $units;
                }
            }
        }

        $service = app(ProjectionScenarioService::class);
        $scenario = $service->create([
            'name' => $name,
            'status' => ProjectionScenarioStatus::Active->value,
            'start_year' => 2026,
            'end_year' => 2030,
            'contact_one_id' => null,
            'contact_two_id' => null,
            'notes' => 'Imported from VVR Full Projections (1).xlsx. All projected pay uses the governed 20% VVR / 40% Contact 1 / 40% Contact 2 split.',
        ], $owner);
        $service->update($scenario, [
            'name' => $name,
            'status' => ProjectionScenarioStatus::Active->value,
            'start_year' => 2026,
            'end_year' => 2030,
            'contact_one_id' => null,
            'contact_two_id' => null,
            'notes' => $scenario->notes,
            'assumptions' => [
                'land_flip' => 10000,
                'property_flip' => 40000,
                'rental' => 1200,
                'surplus' => 1200,
            ],
            'entries' => $entries,
        ], $owner);
    }
}
