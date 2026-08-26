<?php

namespace Tests\Feature;

use App\Contracts\ToolExecutorInterface;
use App\Enums\SopDepartment;
use App\Enums\SopStatus;
use App\Enums\UserRole;
use App\Models\Sop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SopWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_searchable_written_sop(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->post(route('sops.store'), $this->validData())->assertRedirect();

        $sop = Sop::query()->sole();
        $this->assertSame('Property Research Procedure', $sop->title);
        $this->assertSame($owner->id, $sop->created_by);
        $this->actingAs($owner)->get(route('sops.index', ['search' => 'county GIS']))->assertOk()->assertSee($sop->title);
    }

    public function test_private_attachment_is_hashed_downloadable_and_duplicate_safe(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $payload = $this->validData(['content_text' => null, 'sop_file' => UploadedFile::fake()->createWithContent('research.txt', 'Check county GIS and property card.')]);
        $this->actingAs($owner)->post(route('sops.store'), $payload)->assertRedirect();
        $sop = Sop::query()->sole();
        Storage::disk('local')->assertExists($sop->path);
        $this->assertSame(hash('sha256', 'Check county GIS and property card.'), $sop->sha256);
        $this->actingAs($owner)->get(route('sops.download', $sop))->assertOk()->assertHeader('x-content-type-options', 'nosniff');

        $this->actingAs($owner)->post(route('sops.store'), [...$this->validData(['title' => 'Duplicate']), 'sop_file' => UploadedFile::fake()->createWithContent('copy.txt', 'Check county GIS and property card.')])->assertSessionHasErrors('sop_file');
        $this->assertDatabaseCount('sops', 1);
    }

    public function test_non_manager_can_read_but_cannot_change_sops(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $sop = Sop::factory()->create();
        $this->actingAs($user)->get(route('sops.show', $sop))->assertOk();
        $this->actingAs($user)->get(route('sops.create'))->assertForbidden();
        $this->actingAs($user)->put(route('sops.update', $sop), $this->validData())->assertForbidden();
    }

    public function test_manager_can_assign_a_next_sop_and_users_can_continue_from_the_bottom(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $current = Sop::factory()->create(['title' => 'Research the Property']);
        $next = Sop::factory()->create([
            'title' => 'Contact the Owner',
            'department' => SopDepartment::Acquisitions,
            'status' => SopStatus::Active,
            'summary' => 'Begin authorized owner outreach.',
        ]);

        $this->actingAs($owner)->put(route('sops.update', $current), $this->validData([
            'title' => $current->title,
            'next_sop_id' => $next->id,
        ]))->assertRedirect(route('sops.show', $current));

        $this->assertSame($next->id, $current->fresh()->next_sop_id);
        $this->actingAs($user)->get(route('sops.show', $current))
            ->assertOk()
            ->assertSee('Next SOP: Contact the Owner')
            ->assertSee(route('sops.show', $next), false)
            ->assertSee('Open next SOP');
    }

    public function test_sop_cannot_assign_itself_as_the_next_sop(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $sop = Sop::factory()->create();

        $this->actingAs($owner)->put(route('sops.update', $sop), $this->validData([
            'next_sop_id' => $sop->id,
        ]))->assertSessionHasErrors('next_sop_id');

        $this->assertNull($sop->fresh()->next_sop_id);
    }

    public function test_archived_sop_cannot_be_assigned_as_the_next_sop(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $sop = Sop::factory()->create();
        $archived = Sop::factory()->create();
        $archived->delete();

        $this->actingAs($owner)->put(route('sops.update', $sop), $this->validData([
            'next_sop_id' => $archived->id,
        ]))->assertSessionHasErrors('next_sop_id');

        $this->assertNull($sop->fresh()->next_sop_id);
    }

    public function test_next_sop_assignment_cannot_create_a_circular_sequence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $first = Sop::factory()->create(['title' => 'First SOP']);
        $second = Sop::factory()->create(['title' => 'Second SOP', 'next_sop_id' => $first->id]);

        $this->actingAs($owner)->put(route('sops.update', $first), $this->validData([
            'title' => $first->title,
            'next_sop_id' => $second->id,
        ]))->assertSessionHasErrors('next_sop_id');

        $this->assertNull($first->fresh()->next_sop_id);
    }

    public function test_partner_can_manage_but_only_owner_or_admin_can_archive(): void
    {
        $partner = User::factory()->create(['role' => UserRole::Partner]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $sop = Sop::factory()->create();
        $this->actingAs($partner)->put(route('sops.update', $sop), $this->validData(['version_label' => '1.1']))->assertRedirect();
        $this->actingAs($partner)->delete(route('sops.destroy', $sop))->assertForbidden();
        $this->actingAs($owner)->delete(route('sops.destroy', $sop))->assertRedirect(route('sops.index'));
        $this->assertSoftDeleted($sop);
    }

    public function test_sop_requires_written_content_file_or_drive_source(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->post(route('sops.store'), $this->validData(['content_text' => null, 'drive_url' => null]))->assertSessionHasErrors('sop_file');
    }

    public function test_ai_can_search_and_read_sops_without_receiving_write_tools(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $sop = Sop::factory()->create(['title' => 'Property Research SOP', 'content_text' => 'Open the county GIS before reviewing the property card.']);
        $executor = app(ToolExecutorInterface::class);

        $results = $executor->execute('search_sops', ['search' => 'county GIS'], $user);
        $this->assertSame($sop->id, $results['records'][0]['id']);
        $record = $executor->execute('get_sop', ['sop_id' => $sop->id], $user);
        $this->assertStringContainsString('county GIS', $record['record']['procedure']);
        $this->assertNull(app(\App\Contracts\ToolRegistryInterface::class)->find('update_sop'));
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Property Research Procedure', 'department' => SopDepartment::Research->value,
            'status' => SopStatus::Active->value, 'version_label' => '1.0',
            'summary' => 'How VVR researches a property.', 'content_text' => "1. Open the county GIS.\n2. Review the property card.",
            'effective_date' => now()->toDateString(), 'review_date' => now()->addYear()->toDateString(),
        ], $overrides);
    }
}
