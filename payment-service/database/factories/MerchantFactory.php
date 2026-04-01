<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Merchant> */
class MerchantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'webhook_url' => null,
        ];
    }

    public function withWebhook(): static
    {
        return $this->state(fn (): array => [
            'webhook_url' => fake()->url(),
        ]);
    }
}
