<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\PropertyIntakeFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyDocumentIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_attached_ai_source_remains_private_and_policy_protected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $readOnly = User::factory()->create(['role' => UserRole::ReadOnly]);
        $property = Property::factory()->create();
        Storage::disk('local')->put('property-intakes/source.pdf', 'private source');
        $file = PropertyIntakeFile::query()->create([
            'token' => fake()->uuid(),
            'user_id' => $owner->id,
            'property_id' => $property->id,
            'disk' => 'local',
            'path' => 'property-intakes/source.pdf',
            'original_name' => 'source.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 14,
            'sha256' => hash('sha256', 'private source'),
            'status' => 'attached',
            'source_mode' => 'prompt_and_document',
            'attached_at' => now(),
        ]);

        $this->actingAs($readOnly)->get(route('properties.intake-files.download', [$property, $file]))->assertForbidden();
        $this->actingAs($owner)->get(route('properties.intake-files.download', [$property, $file]))->assertDownload('source.pdf');
    }
}
