<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'idempotency_key' => fake()->uuid(),
            'type' => TransactionType::Payment,
            'amount' => fake()->randomFloat(2, 1, 1000),
            'currency' => 'USD',
            'status' => TransactionStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatus::Approved,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatus::Rejected,
        ]);
    }

    public function refund(?Transaction $original = null): static
    {
        return $this->state(fn (): array => array_filter([
            'type' => TransactionType::Refund,
            'original_transaction_id' => $original?->id,
        ]));
    }
}
