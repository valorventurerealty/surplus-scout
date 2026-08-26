<?php

namespace Tests\Feature;

use App\Enums\ArmoryScriptCategory;
use App\Enums\ArmoryScriptStatus;
use App\Enums\UserRole;
use App\Models\ArmoryScript;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArmoryWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_armory(): void
    {
        $this->get(route('armory.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_browse_armory(): void
    {
        $script = ArmoryScript::factory()->create(['title' => 'Seller Discovery Script']);

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('armory.index'))
                ->assertOk()
                ->assertSee('Seller Discovery Script')
                ->assertSee('Interactive script operations');
        }
    }

    public function test_scripts_can_be_sorted_by_clickable_columns(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        ArmoryScript::factory()->create(['title' => 'Zulu Script']);
        ArmoryScript::factory()->create(['title' => 'Alpha Script']);

        $this->actingAs($user)->get(route('armory.index', ['sort' => 'script', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Script', 'Zulu Script']);

        $this->actingAs($user)->get(route('armory.index', ['sort' => 'script', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Zulu Script', 'Alpha Script']);
    }

    public function test_authorized_user_can_upload_private_searchable_script(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $content = "Opening\nWhat prompted you to sell?";
        $file = UploadedFile::fake()->createWithContent('seller-call.txt', $content);

        $response = $this->actingAs($user)->post(route('armory.store'), [
            'title' => 'Seller Discovery Script',
            'category' => ArmoryScriptCategory::SellerCalls->value,
            'status' => ArmoryScriptStatus::Active->value,
            'version_label' => '1.0',
            'description' => 'Initial seller qualification call.',
            'script_file' => $file,
        ]);

        $script = ArmoryScript::query()->firstOrFail();
        $response->assertRedirect(route('armory.show', $script));
        $this->assertSame($content, $script->content_text);
        $this->assertSame(hash('sha256', $content), $script->sha256);
        $this->assertSame($user->id, $script->uploaded_by);
        Storage::disk('local')->assertExists($script->path);
        $this->assertStringStartsWith('armory/', $script->path);
        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'created',
            'auditable_type' => $script->getMorphClass(),
            'auditable_id' => $script->id,
        ]);

        $this->actingAs($user)->get(route('armory.download', $script))->assertDownload('seller-call.txt');
        $this->actingAs($user)->get(route('armory.index', ['search' => 'prompted you']))
            ->assertOk()->assertSee('Seller Discovery Script');
    }

    public function test_text_only_script_can_be_created_and_escaped_when_displayed(): void
    {
        $user = User::factory()->create(['role' => UserRole::Marketing]);

        $this->actingAs($user)->post(route('armory.store'), [
            'title' => 'Buyer Follow-up',
            'category' => ArmoryScriptCategory::BuyerOutreach->value,
            'status' => ArmoryScriptStatus::Draft->value,
            'version_label' => 'draft-1',
            'content_text' => '<script>alert("unsafe")</script> Follow up tomorrow.',
        ])->assertRedirect();

        $script = ArmoryScript::query()->firstOrFail();
        $this->actingAs($user)->get(route('armory.show', $script))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false);
    }

    public function test_metadata_only_script_can_be_saved_before_guided_steps_are_built(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->post(route('armory.store'), [
            'title' => 'New Guided Seller Script',
            'category' => ArmoryScriptCategory::SellerCalls->value,
            'status' => ArmoryScriptStatus::Draft->value,
            'version_label' => '1.0',
            'description' => 'Guided steps will be configured after creation.',
            'content_text' => '',
        ])->assertRedirect();

        $script = ArmoryScript::query()->sole();
        $this->assertSame('New Guided Seller Script', $script->title);
        $this->assertNull($script->content_text);
        $this->assertFalse($script->hasFile());
        $this->actingAs($user)->get(route('armory.playbook.show', $script))->assertOk()->assertSee('Add guided step');
    }

    public function test_script_save_uses_safe_defaults_for_omitted_optional_metadata(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($user)->post(route('armory.store'), [
            'title' => 'Simple Guided Script',
        ]);

        $script = ArmoryScript::query()->sole();
        $response->assertRedirect(route('armory.show', $script));
        $this->assertSame(ArmoryScriptCategory::Other, $script->category);
        $this->assertSame(ArmoryScriptStatus::Draft, $script->status);
        $this->assertSame('1.0', $script->version_label);
    }

    public function test_human_readable_version_label_is_accepted(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->post(route('armory.store'), [
            'title' => 'Versioned Script',
            'category' => ArmoryScriptCategory::SurplusRecovery->value,
            'status' => ArmoryScriptStatus::Active->value,
            'version_label' => 'Version 1',
        ])->assertRedirect();

        $this->assertDatabaseHas('armory_scripts', [
            'title' => 'Versioned Script',
            'version_label' => 'Version 1',
        ]);
    }

    public function test_duplicate_file_hash_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $data = [
            'title' => 'Original Script',
            'category' => ArmoryScriptCategory::Negotiation->value,
            'status' => ArmoryScriptStatus::Active->value,
            'version_label' => '1.0',
        ];

        $this->actingAs($user)->post(route('armory.store'), [
            ...$data,
            'script_file' => UploadedFile::fake()->createWithContent('first.txt', 'identical content'),
        ])->assertRedirect();

        $this->actingAs($user)->post(route('armory.store'), [
            ...$data,
            'title' => 'Duplicate Script',
            'script_file' => UploadedFile::fake()->createWithContent('second.txt', 'identical content'),
        ])->assertSessionHasErrors('script_file');

        $this->assertSame(1, ArmoryScript::query()->count());
    }

    public function test_virtual_assistant_and_read_only_user_cannot_manage_armory(): void
    {
        $script = ArmoryScript::factory()->create();

        foreach ([UserRole::VirtualAssistant, UserRole::ReadOnly] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('armory.show', $script))->assertOk();
            $this->actingAs($user)->get(route('armory.create'))->assertForbidden();
            $this->actingAs($user)->put(route('armory.update', $script), [])->assertForbidden();
            $this->actingAs($user)->delete(route('armory.destroy', $script))->assertForbidden();
        }

        $this->assertNotSoftDeleted($script);
    }

    public function test_archiving_retains_private_file_for_audit_and_recovery(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($user)->post(route('armory.store'), [
            'title' => 'Retained Script',
            'category' => ArmoryScriptCategory::Training->value,
            'status' => ArmoryScriptStatus::Retired->value,
            'version_label' => '1.0',
            'script_file' => UploadedFile::fake()->createWithContent('retained.txt', 'retain me'),
        ]);
        $script = ArmoryScript::query()->firstOrFail();

        $this->actingAs($user)->delete(route('armory.destroy', $script))
            ->assertRedirect(route('armory.index'));

        $this->assertSoftDeleted($script);
        Storage::disk('local')->assertExists($script->path);
    }
}
