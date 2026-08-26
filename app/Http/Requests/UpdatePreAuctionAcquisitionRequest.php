<?php

namespace App\Http\Requests;

class UpdatePreAuctionAcquisitionRequest extends PreAuctionAcquisitionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('preAuction')) ?? false;
    }
}
