<?php

namespace App\Enums;

enum SopDepartment: string
{
    case GeneralOperations = 'general_operations';
    case Acquisitions = 'acquisitions';
    case Dispositions = 'dispositions';
    case SurplusRecovery = 'surplus_recovery';
    case PreTaxAuctions = 'pre_tax_auctions';
    case Research = 'research';
    case Marketing = 'marketing';
    case Financials = 'financials';
    case Administration = 'administration';
    case Technology = 'technology';
    case Compliance = 'compliance';

    public function label(): string
    {
        return match ($this) {
            self::PreTaxAuctions => 'PreTax Auctions',
            default => str($this->value)->headline()->toString(),
        };
    }
}
