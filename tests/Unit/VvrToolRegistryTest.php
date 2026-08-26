<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\VvrToolRegistry;
use Tests\TestCase;

class VvrToolRegistryTest extends TestCase
{
    public function test_read_only_users_receive_no_write_tools(): void
    {
        $user = new User(['role' => UserRole::ReadOnly, 'is_active' => true]);
        $tools = collect((new VvrToolRegistry())->forUser($user));

        $this->assertTrue($tools->contains('name', 'get_properties'));
        $this->assertTrue($tools->contains('name', 'search_buyers'));
        $this->assertTrue($tools->contains('name', 'search_sops'));
        $this->assertFalse($tools->contains('name', 'create_property'));
        $this->assertFalse($tools->contains('name', 'create_task'));
    }

    public function test_write_tools_require_approval_and_surplus_tools_are_role_scoped(): void
    {
        $registry = new VvrToolRegistry();

        $this->assertTrue($registry->find('create_property')->requiresApproval);
        $this->assertSame(2, $registry->find('change_pipeline_stage')->riskLevel);
        $this->assertTrue($registry->find('get_surplus_case')->enabled);
        $this->assertTrue($registry->find('update_surplus_case')->enabled);
        $this->assertTrue($registry->find('update_surplus_case')->requiresApproval);
        $marketing = new User(['role' => UserRole::Marketing, 'is_active' => true]);
        $this->assertFalse(collect($registry->forUser($marketing))->contains('name', 'get_surplus_case'));
    }
}
