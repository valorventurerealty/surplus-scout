<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Partner = 'partner';
    case AcquisitionManager = 'acquisition_manager';
    case DispositionManager = 'disposition_manager';
    case VirtualAssistant = 'virtual_assistant';
    case Marketing = 'marketing';
    case Admin = 'admin';
    case ReadOnly = 'read_only';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Partner => 'Partner',
            self::AcquisitionManager => 'Acquisition Manager',
            self::DispositionManager => 'Disposition Manager',
            self::VirtualAssistant => 'Virtual Assistant',
            self::Marketing => 'Marketing',
            self::Admin => 'Admin',
            self::ReadOnly => 'Read Only',
        };
    }

    public function canManageContacts(): bool
    {
        return $this !== self::ReadOnly;
    }

    public function isAdministrator(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageProperties(): bool
    {
        return in_array($this, [
            self::Owner, self::Partner, self::AcquisitionManager, self::DispositionManager,
            self::VirtualAssistant, self::Admin,
        ], true);
    }

    public function canViewPropertyFinancials(): bool
    {
        return in_array($this, [
            self::Owner, self::Partner, self::AcquisitionManager, self::DispositionManager, self::Admin,
        ], true);
    }

    public function canManageProjections(): bool
    {
        return in_array($this, [self::Owner, self::Partner, self::Admin], true);
    }

    public function canArchiveProjections(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canViewPropertySourceDocuments(): bool
    {
        return $this->canViewPropertyFinancials();
    }

    public function canViewContactSourceDocuments(): bool
    {
        return $this->canViewPropertyFinancials();
    }

    public function canManageArmory(): bool
    {
        return in_array($this, [
            self::Owner, self::Partner, self::AcquisitionManager, self::DispositionManager,
            self::Marketing, self::Admin,
        ], true);
    }

    public function canSendEmail(): bool
    {
        return $this !== self::ReadOnly;
    }

    public function canViewAllOutboundEmails(): bool
    {
        return in_array($this, [self::Owner, self::Partner, self::Admin], true);
    }

    public function canManageEmailSettings(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageSops(): bool
    {
        return in_array($this, [self::Owner, self::Partner, self::Admin], true);
    }

    public function canArchiveSops(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageTasks(): bool
    {
        return $this !== self::ReadOnly;
    }

    public function canManageTaskTemplates(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageCalendar(): bool
    {
        return $this->canManageProperties();
    }

    public function canManageDeals(): bool
    {
        return in_array($this, [
            self::Owner, self::Partner, self::AcquisitionManager, self::DispositionManager, self::Admin,
        ], true);
    }

    public function canUseVvrAi(): bool
    {
        return true;
    }

    public function canViewSurplusCases(): bool
    {
        return $this !== self::Marketing;
    }

    public function canManageSurplusCases(): bool
    {
        return in_array($this, [
            self::Owner, self::Partner, self::AcquisitionManager, self::VirtualAssistant, self::Admin,
        ], true);
    }

    public function canViewSurplusFinancials(): bool
    {
        return in_array($this, [self::Owner, self::Partner, self::AcquisitionManager, self::Admin], true);
    }

    public function canArchiveSurplusCases(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canViewPreAuctionAcquisitions(): bool
    {
        return $this !== self::Marketing;
    }

    public function canManagePreAuctionAcquisitions(): bool
    {
        return in_array($this, [
            self::Owner, self::Partner, self::AcquisitionManager, self::VirtualAssistant, self::Admin,
        ], true);
    }

    public function canViewPreAuctionFinancials(): bool
    {
        return in_array($this, [self::Owner, self::Partner, self::AcquisitionManager, self::Admin], true);
    }

    public function canArchivePreAuctionAcquisitions(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canArchiveDeals(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canArchiveProperties(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
