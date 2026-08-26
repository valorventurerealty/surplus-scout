<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DealService
{
    public function create(array $data, User $actor): Deal
    {
        return DB::transaction(function () use ($data, $actor): Deal {
            $deal = Deal::query()->create([...$data, 'token' => (string) Str::uuid(), 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $deal->update(['deal_number' => 'VVR-'.now()->format('Y').'-'.str_pad((string) $deal->id, 6, '0', STR_PAD_LEFT)]);
            return $deal->refresh();
        });
    }

    public function update(Deal $deal, array $data, User $actor): Deal
    {
        return DB::transaction(function () use ($deal, $data, $actor): Deal {
            $deal->update([...$data, 'updated_by' => $actor->id]);
            return $deal->refresh();
        });
    }
}
