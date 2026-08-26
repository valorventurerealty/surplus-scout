<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ContactDirectoryQuery
{
    public function build(User $user, array $filters): Builder
    {
        $query = Contact::query()
            ->withCount(['tasks as open_tasks_count' => fn ($query) => $query->open()])
            ->when(! $user->canViewSurplusCases(), fn (Builder $query) => $query->where('type', '!=', ContactType::Surplus->value))
            ->when(! $user->canViewPreAuctionAcquisitions(), fn (Builder $query) => $query->where('type', '!=', ContactType::PreTaxAuctions->value))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        match ($filters['sort'] ?? 'created_at') {
            'name' => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction),
            'company' => $query->orderBy('company', $direction),
            'email' => $query->orderBy('email', $direction),
            'associated_tasks' => $query->orderBy('open_tasks_count', $direction),
            'next_follow_up' => $query->orderBy('next_follow_up_at', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        return $query->orderBy('id', $direction);
    }
}
