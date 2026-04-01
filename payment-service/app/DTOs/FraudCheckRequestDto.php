<?php

namespace App\DTOs;

use App\Models\Transaction;

readonly class FraudCheckRequestDto
{
    public function __construct(
        public string $transactionId,
        public string $merchantId,
        public string $type,
        public float $amount,
        public string $currency,
        public string $idempotencyKey,
    ) {}

    public static function fromTransaction(Transaction $transaction): self
    {
        return new self(
            transactionId: $transaction->id,
            merchantId: $transaction->merchant_id,
            type: $transaction->type->value,
            amount: (float) $transaction->amount,
            currency: $transaction->currency,
            idempotencyKey: $transaction->idempotency_key,
        );
    }

    /** @return array{transaction_id: string, merchant_id: string, type: string, amount: float, currency: string, idempotency_key: string} */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'merchant_id' => $this->merchantId,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
