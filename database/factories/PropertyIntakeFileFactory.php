<?php

namespace Database\Factories;

use App\Models\PropertyIntakeFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyIntakeFile> */
class PropertyIntakeFileFactory extends Factory
{
    public function definition(): array
    {
        $token = fake()->uuid();

        return [
            'token' => $token,
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'property-intakes/testing/'.$token.'.pdf',
            'original_name' => 'fictional-property-record.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => fake()->sha256(),
            'request_fingerprint' => fake()->sha256(),
            'status' => 'ready',
            'source_mode' => 'document',
            'provider' => 'fake',
            'model' => 'fake-extraction-model',
            'extraction_json' => ['fields' => [], 'missing_fields' => [], 'warnings' => []],
            'expires_at' => now()->addDay(),
        ];
    }
}
