<?php

namespace Tests\Feature\SalesCopilot;

use App\Enums\UserRole;
use App\Models\SalesCopilotSession;
use App\Models\User;
use Database\Seeders\SalesCopilotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesCopilotAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SalesCopilotSeeder::class);
        config()->set('ai.api_key', null);
    }

    public static function objections(): array
    {
        return [
            'scam'=>['This sounds like a scam','legitimacy_concern','When you say scam'],
            'diy'=>['I can do it myself','diy_concern','What have you already looked into'],
            'information'=>['Send me information','information_request','what exactly are you looking for'],
            'decision maker'=>['I need to talk to my wife','decision_maker_concern','involved in the decision'],
            'too good'=>['It sounds too good to be true','too_good_to_be_true','which part feels that way'],
            'not interested'=>['I am not interested','not_interested','already handled the funds'],
            'comparison'=>['What makes you different from other companies?','competitor_comparison','Well... maybe nothing'],
            'get back'=>['I will get back to you','timing_concern','specific time'],
            'later'=>['Call me later','timing_concern','specific time on the calendar'],
        ];
    }

    #[DataProvider('objections')]
    public function test_canonical_objections_return_governed_coaching(string $statement,string $classification,string $fragment): void
    {
        $user=User::factory()->create(['role'=>UserRole::Owner]);
        $response=$this->actingAs($user)->post(route('sales-copilot.sessions.store'),['prospect_statement'=>$statement]);
        $session=SalesCopilotSession::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('sales-copilot.sessions.show',$session));
        $turn=$session->turns()->firstOrFail();
        $this->assertSame($classification,$turn->classification);
        $this->assertStringContainsString($fragment,$turn->response['recommended_response']);
        $this->assertLessThanOrEqual(1,substr_count($turn->response['recommended_response'],'?'));
    }

    public function test_new_fee_concern_replaces_prior_timing_concern(): void
    {
        $user=User::factory()->create(['role'=>UserRole::Owner]);
        $session=SalesCopilotSession::query()->create(['user_id'=>$user->id,'status'=>'active','current_stage'=>'objection_resolution','state'=>['concerns_raised'=>['timing_concern']]]);
        $this->actingAs($user)->post(route('sales-copilot.sessions.coach',$session),['prospect_statement'=>'I need to think about the 12%']);
        $turn=$session->turns()->firstOrFail();
        $this->assertSame('price_fee_concern',$turn->classification);
        $this->assertStringContainsString('12%',$turn->response['recommended_response']);
    }

    public function test_legal_question_is_escalated_without_an_invented_answer(): void
    {
        $user=User::factory()->create(['role'=>UserRole::Owner]);
        $this->actingAs($user)->post(route('sales-copilot.sessions.store'),['prospect_statement'=>'What Florida statute says I am legally entitled?']);
        $turn=SalesCopilotSession::query()->latest('id')->firstOrFail()->turns()->firstOrFail();
        $this->assertTrue($turn->requires_legal_review);
        $this->assertStringContainsString('do not want to guess',$turn->response['recommended_response']);
    }

    public function test_do_not_contact_ends_persuasion(): void
    {
        $user=User::factory()->create(['role'=>UserRole::Owner]);
        $this->actingAs($user)->post(route('sales-copilot.sessions.store'),['prospect_statement'=>'Stop calling me and remove me']);
        $session=SalesCopilotSession::query()->latest('id')->firstOrFail();
        $this->assertSame('do_not_contact',$session->state['next_action']);
        $this->assertSame('completed',$session->status);
        $this->assertSame('do_not_contact',$session->turns()->firstOrFail()->classification);
    }

    public function test_user_cannot_open_another_users_session(): void
    {
        $owner=User::factory()->create(['role'=>UserRole::ReadOnly]);
        $other=User::factory()->create(['role'=>UserRole::ReadOnly]);
        $session=SalesCopilotSession::query()->create(['user_id'=>$owner->id,'status'=>'active']);
        $this->actingAs($other)->get(route('sales-copilot.sessions.show',$session))->assertForbidden();
    }
}
