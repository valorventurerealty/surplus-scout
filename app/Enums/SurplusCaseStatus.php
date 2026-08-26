<?php

namespace App\Enums;

enum SurplusCaseStatus: string
{
    case Research = 'research';
    case LocateOwner = 'locate_owner';
    case MailerSent = 'mailer_sent';
    case Contact = 'contact';
    case Agreement = 'agreement';
    case SubmitClaim = 'submit_claim';
    case Approved = 'approved';
    case Paid = 'paid';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::LocateOwner => 'Locate Owner',
            self::MailerSent => 'Mailer Sent',
            self::SubmitClaim => 'Submit Claim',
            default => str($this->value)->headline()->toString(),
        };
    }

    /** @return list<string> */
    public static function orderedValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }
}
