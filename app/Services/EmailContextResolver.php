<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\PreAuctionAcquisition;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EmailContextResolver
{
    private const TYPES = [
        'contact' => Contact::class,
        'property' => Property::class,
        'deal' => Deal::class,
        'surplus' => SurplusCase::class,
        'pre_auction' => PreAuctionAcquisition::class,
    ];

    public function resolve(?string $type, mixed $id, User $user): ?Model
    {
        if (! $type) return null;
        $class = self::TYPES[$type] ?? throw ValidationException::withMessages(['related_type' => 'Unsupported related record.']);
        $model = $class::query()->find($id) ?? throw ValidationException::withMessages(['related_id' => 'The related record no longer exists.']);
        Gate::forUser($user)->authorize('view', $model);
        return $model;
    }

    public function contact(?Model $record): ?Contact
    {
        return match (true) {
            $record instanceof Contact => $record,
            $record instanceof Property => $record->ownerContact,
            $record instanceof Deal => $record->primaryContact,
            $record instanceof SurplusCase => $record->claimantContact,
            $record instanceof PreAuctionAcquisition => $record->ownerContact,
            default => null,
        };
    }

    public function type(?Model $record): ?string
    {
        foreach (self::TYPES as $type => $class) if ($record instanceof $class) return $type;
        return null;
    }
}
