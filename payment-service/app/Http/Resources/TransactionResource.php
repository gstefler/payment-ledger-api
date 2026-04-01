<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'idempotency_key' => $this->idempotency_key,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'original_transaction_id' => $this->original_transaction_id,
            'fraud_reason' => $this->fraud_reason,
            'created_at' => $this->created_at,
        ];
    }
}
