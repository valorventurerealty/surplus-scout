<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_only_read_only_role_cannot_manage_contacts(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame($role !== UserRole::ReadOnly, $role->canManageContacts());
        }
    }

    public function test_owner_and_admin_are_administrators(): void
    {
        $this->assertTrue(UserRole::Owner->isAdministrator());
        $this->assertTrue(UserRole::Admin->isAdministrator());
        $this->assertFalse(UserRole::Partner->isAdministrator());
    }

    public function test_armory_management_roles_are_explicit(): void
    {
        foreach ([UserRole::Owner, UserRole::Partner, UserRole::AcquisitionManager, UserRole::DispositionManager, UserRole::Marketing, UserRole::Admin] as $role) {
            $this->assertTrue($role->canManageArmory());
        }

        $this->assertFalse(UserRole::VirtualAssistant->canManageArmory());
        $this->assertFalse(UserRole::ReadOnly->canManageArmory());
    }

    public function test_email_permissions_are_explicit(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame($role !== UserRole::ReadOnly, $role->canSendEmail());
        }

        foreach ([UserRole::Owner, UserRole::Partner, UserRole::Admin] as $role) {
            $this->assertTrue($role->canViewAllOutboundEmails());
        }
        $this->assertFalse(UserRole::Marketing->canViewAllOutboundEmails());
        $this->assertTrue(UserRole::Owner->canManageEmailSettings());
        $this->assertTrue(UserRole::Admin->canManageEmailSettings());
        $this->assertFalse(UserRole::Partner->canManageEmailSettings());
    }

    public function test_pre_auction_permissions_are_explicit(): void
    {
        $this->assertFalse(UserRole::Marketing->canViewPreAuctionAcquisitions());
        $this->assertTrue(UserRole::ReadOnly->canViewPreAuctionAcquisitions());
        $this->assertTrue(UserRole::VirtualAssistant->canManagePreAuctionAcquisitions());
        $this->assertFalse(UserRole::ReadOnly->canManagePreAuctionAcquisitions());
        $this->assertTrue(UserRole::Owner->canViewPreAuctionFinancials());
        $this->assertFalse(UserRole::VirtualAssistant->canViewPreAuctionFinancials());
        $this->assertTrue(UserRole::Admin->canArchivePreAuctionAcquisitions());
        $this->assertFalse(UserRole::Partner->canArchivePreAuctionAcquisitions());
    }
}
