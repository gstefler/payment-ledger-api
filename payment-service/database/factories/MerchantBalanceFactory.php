<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MerchantBalance> */
class MerchantBalanceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'currency' => 'USD',
            'balance' => 0,
        ];
    }
}
