<?php

namespace Tests\Feature;

use App\Enums\ArmoryScriptStatus;
use App\Enums\ArmorySessionStatus;
use App\Enums\UserRole;
use App\Models\ArmoryScript;
use App\Models\ArmoryScriptStep;
use App\Models\ArmoryScriptStepOption;
use App\Models\ArmorySession;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InteractiveArmoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_build_branching_playbook(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $script = ArmoryScript::factory()->create();

        $this->actingAs($manager)->post(route('armory.playbook.steps.store', $script), [
            'title' => 'Opening', 'prompt_text' => 'Hello {{contact_name}}', 'sequence' => 10,
        ])->assertRedirect(route('armory.playbook.show', $script));
        $step = $script->steps()->firstOrFail();

        $this->actingAs($manager)->post(route('armory.playbook.options.store', $step), [
            'label' => 'Interested', 'response_text' => 'Great.', 'outcome_code' => 'qualified', 'sequence' => 10,
        ])->assertRedirect(route('armory.playbook.show', $script));

        $this->assertDatabaseHas('armory_script_step_options', [
            'armory_script_step_id' => $step->id,
            'label' => 'Interested',
            'outcome_code' => 'qualified',
        ]);
        $this->actingAs($manager)->get(route('armory.playbook.show', $script))
            ->assertOk()
            ->assertSee('action="'.route('armory.playbook.steps.update', $step, false).'"', false)
            ->assertDontSee('Default next stage')
            ->assertDontSee('Starting step in next stage');
    }

    public function test_cross_script_transition_columns_are_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('armory_scripts', 'default_next_script_id'));
        $this->assertFalse(Schema::hasColumn('armory_scripts', 'default_next_script_step_id'));
        $this->assertFalse(Schema::hasColumn('armory_script_step_options', 'next_script_id'));
        $this->assertFalse(Schema::hasColumn('armory_script_step_options', 'next_script_step_id'));
        $this->assertFalse(Schema::hasColumn('armory_sessions', 'started_armory_script_id'));
    }

    public function test_user_can_run_branch_and_complete_session_with_context(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant, 'name' => 'VA One']);
        $script = ArmoryScript::factory()->create(['status' => ArmoryScriptStatus::Active]);
        $step = ArmoryScriptStep::factory()->create(['armory_script_id' => $script->id, 'prompt_text' => 'Hello {{contact_name}}', 'sequence' => 10]);
        $option = ArmoryScriptStepOption::factory()->create(['armory_script_step_id' => $step->id, 'label' => 'Ready', 'outcome_code' => 'qualified']);
        $contact = Contact::factory()->create(['first_name' => 'Taylor', 'last_name' => 'Seller']);
        $property = Property::factory()->create(['address' => '120 Bayberry Road']);

        $this->actingAs($user)->post(route('armory.sessions.store', $script), ['contact_id' => $contact->id, 'property_id' => $property->id])->assertRedirect();
        $session = ArmorySession::query()->firstOrFail();
        $this->actingAs($user)->get(route('armory.sessions.show', $session))->assertOk()->assertSee('Hello Taylor Seller')->assertSee('120 Bayberry Road');
        $this->actingAs($user)->get(route('armory.sessions.show', $session))
            ->assertOk()
            ->assertSee('action="'.route('armory.sessions.advance', $session, false).'"', false);
        $this->actingAs($user)->post(route('armory.sessions.advance', $session), ['option_id' => $option->id, 'step_notes' => 'Ready next week.'])
            ->assertRedirect(route('armory.sessions.show', $session));

        $session->refresh();
        $this->assertSame(ArmorySessionStatus::Completed, $session->status);
        $this->assertSame('qualified', $session->outcome);
        $this->assertStringContainsString('Ready next week.', $session->notes);
    }

    public function test_response_branch_can_move_to_another_step_in_the_same_script(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $script = ArmoryScript::factory()->create(['status' => ArmoryScriptStatus::Active]);
        $opening = ArmoryScriptStep::factory()->create(['armory_script_id' => $script->id, 'sequence' => 10]);
        $target = ArmoryScriptStep::factory()->create(['armory_script_id' => $script->id, 'sequence' => 20]);
        $option = ArmoryScriptStepOption::factory()->create([
            'armory_script_step_id' => $opening->id,
            'next_step_id' => $target->id,
            'sequence' => 10,
        ]);
        $session = ArmorySession::factory()->create([
            'armory_script_id' => $script->id,
            'user_id' => $user->id,
            'current_step_id' => $opening->id,
        ]);

        $this->actingAs($user)->post(route('armory.sessions.advance', $session), ['option_id' => $option->id])
            ->assertRedirect(route('armory.sessions.show', $session));

        $this->assertSame($target->id, $session->fresh()->current_step_id);
        $this->assertSame(ArmorySessionStatus::InProgress, $session->fresh()->status);
    }

    public function test_option_from_another_step_cannot_be_submitted(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $script = ArmoryScript::factory()->create();
        $current = ArmoryScriptStep::factory()->create(['armory_script_id' => $script->id, 'sequence' => 10]);
        $other = ArmoryScriptStep::factory()->create(['armory_script_id' => $script->id, 'sequence' => 20]);
        ArmoryScriptStepOption::factory()->create(['armory_script_step_id' => $current->id, 'sequence' => 10]);
        $wrongOption = ArmoryScriptStepOption::factory()->create(['armory_script_step_id' => $other->id, 'sequence' => 10]);
        $session = ArmorySession::factory()->create(['armory_script_id' => $script->id, 'user_id' => $user->id, 'current_step_id' => $current->id]);

        $this->actingAs($user)->post(route('armory.sessions.advance', $session), ['option_id' => $wrongOption->id])->assertSessionHasErrors('option_id');
        $this->assertSame($current->id, $session->fresh()->current_step_id);
    }

    public function test_script_used_by_active_session_cannot_be_archived(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $script = ArmoryScript::factory()->create();
        ArmorySession::factory()->create([
            'armory_script_id' => $script->id,
            'user_id' => $owner->id,
            'status' => ArmorySessionStatus::InProgress,
        ]);

        $this->actingAs($owner)->delete(route('armory.destroy', $script))->assertStatus(409);
        $this->assertNotSoftDeleted($script);
    }

    public function test_user_cannot_open_another_users_session(): void
    {
        $owner = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $other = User::factory()->create(['role' => UserRole::ReadOnly]);
        $session = ArmorySession::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($other)->get(route('armory.sessions.show', $session))->assertForbidden();
    }

    public function test_guided_sessions_page_can_launch_an_active_script(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $active = ArmoryScript::factory()->create(['title' => 'Active Seller Call', 'status' => ArmoryScriptStatus::Active]);
        ArmoryScriptStep::factory()->create(['armory_script_id' => $active->id]);
        $draft = ArmoryScript::factory()->create(['title' => 'Private Draft Call', 'status' => ArmoryScriptStatus::Draft]);
        ArmoryScriptStep::factory()->create(['armory_script_id' => $draft->id]);

        $this->actingAs($user)->get(route('armory.sessions.index'))
            ->assertOk()->assertSee('Start a new guided session')->assertSee('Active Seller Call')->assertDontSee('Private Draft Call');
        $this->actingAs($user)->get(route('armory.sessions.start', ['script_id' => $active->id]))
            ->assertRedirect(route('armory.sessions.create', $active));
    }

    public function test_user_can_soft_delete_own_guided_session_but_not_another_users_session(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $other = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $ownSession = ArmorySession::factory()->create(['user_id' => $user->id]);
        $otherSession = ArmorySession::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->delete(route('armory.sessions.destroy', $otherSession))->assertForbidden();
        $this->actingAs($user)->delete(route('armory.sessions.destroy', $ownSession))->assertRedirect(route('armory.sessions.index'));

        $this->assertSoftDeleted($ownSession);
        $this->assertNotSoftDeleted($otherSession);
        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'deleted',
            'auditable_type' => $ownSession->getMorphClass(),
            'auditable_id' => $ownSession->id,
        ]);
    }

    public function test_expired_deleted_sessions_are_pruned(): void
    {
        $session = ArmorySession::factory()->create();
        $session->delete();
        ArmorySession::onlyTrashed()->whereKey($session->id)->update(['deleted_at' => now()->subDays(31)]);

        $this->artisan('armory:prune-deleted-sessions')->assertSuccessful();
        $this->assertDatabaseMissing('armory_sessions', ['id' => $session->id]);
    }

    public function test_script_content_and_context_are_escaped(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $script = ArmoryScript::factory()->create();
        $step = ArmoryScriptStep::factory()->create(['armory_script_id' => $script->id, 'prompt_text' => '<script>alert(1)</script> {{caller_name}}']);
        $session = ArmorySession::factory()->create(['armory_script_id' => $script->id, 'user_id' => $user->id, 'current_step_id' => $step->id, 'caller_name' => '<img src=x onerror=alert(2)>']);
        $this->actingAs($user)->get(route('armory.sessions.show', $session))->assertOk()->assertDontSee('<script>', false)->assertDontSee('<img', false);
    }
}
