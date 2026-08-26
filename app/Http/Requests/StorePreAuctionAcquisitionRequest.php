<?php

namespace App\Http\Requests;

use App\Models\PreAuctionAcquisition;

class StorePreAuctionAcquisitionRequest extends PreAuctionAcquisitionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PreAuctionAcquisition::class) ?? false;
    }
}
