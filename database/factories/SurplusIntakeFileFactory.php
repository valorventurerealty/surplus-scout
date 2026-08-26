<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\SurplusIntakeFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SurplusIntakeFile> */
class SurplusIntakeFileFactory extends Factory
{
    public function definition(): array
    {
        $token = (string) Str::uuid();
        return [
            'token' => $token, 'user_id' => User::factory(), 'ai_conversation_id' => AiConversation::factory(),
            'disk' => 'local', 'path' => 'surplus-intakes/testing/'.$token.'.pdf',
            'original_name' => 'fictional-trim-notice.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 2048, 'sha256' => fake()->sha256(), 'request_fingerprint' => fake()->sha256(),
            'status' => 'ready', 'user_prompt' => 'Create a Surplus case from this tax notice.',
            'provider' => 'fake', 'model' => 'fake-extraction-model',
            'extraction_json' => ['fields' => [], 'missing_fields' => [], 'warnings' => []],
            'expires_at' => now()->addDay(),
        ];
    }
}
