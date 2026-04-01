<?php

namespace App\Http\Resources;

use App\Models\MerchantBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MerchantBalance */
class MerchantBalanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->currency,
            'balance' => $this->balance,
        ];
    }
}
