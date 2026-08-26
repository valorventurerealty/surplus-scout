<?php

namespace Database\Factories;

use App\Models\ContactIntakeFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactIntakeFile> */
class ContactIntakeFileFactory extends Factory
{
    public function definition(): array
    {
        $token = fake()->uuid();

        return [
            'token' => $token,
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'contact-intakes/testing/'.$token.'.pdf',
            'original_name' => 'fictional-contact-record.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => fake()->sha256(),
            'status' => 'ready',
            'provider' => 'fake',
            'model' => 'fake-extraction-model',
            'extraction_json' => ['fields' => [], 'missing_fields' => [], 'warnings' => []],
            'expires_at' => now()->addDay(),
        ];
    }
}
