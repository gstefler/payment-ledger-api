<?php

use App\Models\Merchant;
use App\Models\MerchantBalance;

it('lists all merchants', function (): void {
    Merchant::factory()->count(3)->create();

    $response = $this->getJson('/api/merchants');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a merchant', function (): void {
    $response = $this->postJson('/api/merchants', [
        'name' => 'Acme Corp',
        'email' => 'acme@example.com',
        'webhook_url' => 'https://example.com/webhook',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Acme Corp')
        ->assertJsonPath('data.email', 'acme@example.com')
        ->assertJsonPath('data.balances', [])
        ->assertJsonPath('data.webhook_url', 'https://example.com/webhook');

    $this->assertDatabaseHas('merchants', ['email' => 'acme@example.com']);
});

it('shows merchant with balances per currency', function (): void {
    $merchant = Merchant::factory()->create();
    MerchantBalance::factory()->create(['merchant_id' => $merchant->id, 'currency' => 'USD', 'balance' => 500]);
    MerchantBalance::factory()->create(['merchant_id' => $merchant->id, 'currency' => 'EUR', 'balance' => 200]);

    $response = $this->getJson("/api/merchants/{$merchant->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data.balances')
        ->assertJsonFragment(['currency' => 'USD', 'balance' => '500.00'])
        ->assertJsonFragment(['currency' => 'EUR', 'balance' => '200.00']);
});

it('validates required fields when creating a merchant', function (): void {
    $response = $this->postJson('/api/merchants', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);
});

it('validates unique email when creating a merchant', function (): void {
    Merchant::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/merchants', [
        'name' => 'Another Corp',
        'email' => 'taken@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('shows a single merchant', function (): void {
    $merchant = Merchant::factory()->create();

    $response = $this->getJson("/api/merchants/{$merchant->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $merchant->id)
        ->assertJsonPath('data.name', $merchant->name);
});
