<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Merchant */
class MerchantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'balances' => MerchantBalanceResource::collection($this->whenLoaded('balances', $this->balances)),
            'webhook_url' => $this->webhook_url,
            'created_at' => $this->created_at,
        ];
    }
}
