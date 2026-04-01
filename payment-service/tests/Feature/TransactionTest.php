<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\ProcessFraudCheck;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Support\Facades\Queue;

it('creates a transaction and dispatches fraud check', function (): void {
    Queue::fake();

    $merchant = Merchant::factory()->create();

    $response = $this->postJson('/api/transactions', [
        'merchant_id' => $merchant->id,
        'idempotency_key' => 'unique-key-1',
        'type' => 'payment',
        'amount' => 100.00,
        'currency' => 'USD',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', '100.00');

    Queue::assertPushedOn('fraud-check', ProcessFraudCheck::class);
});

it('returns existing transaction for duplicate idempotency key', function (): void {
    Queue::fake();

    $merchant = Merchant::factory()->create();
    $transaction = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'idempotency_key' => 'duplicate-key',
    ]);

    $response = $this->postJson('/api/transactions', [
        'merchant_id' => $merchant->id,
        'idempotency_key' => 'duplicate-key',
        'type' => 'payment',
        'amount' => 200.00,
        'currency' => 'USD',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $transaction->id);

    Queue::assertNothingPushed();
});

it('validates required fields when creating a transaction', function (): void {
    $response = $this->postJson('/api/transactions', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['merchant_id', 'idempotency_key', 'type', 'amount', 'currency']);
});

it('validates merchant must exist', function (): void {
    $response = $this->postJson('/api/transactions', [
        'merchant_id' => '00000000-0000-0000-0000-000000000000',
        'idempotency_key' => 'key-1',
        'type' => 'payment',
        'amount' => 100,
        'currency' => 'USD',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['merchant_id']);
});

it('requires original_transaction_id for refunds', function (): void {
    $merchant = Merchant::factory()->create();

    $response = $this->postJson('/api/transactions', [
        'merchant_id' => $merchant->id,
        'idempotency_key' => 'refund-key-1',
        'type' => 'refund',
        'amount' => 50.00,
        'currency' => 'USD',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['original_transaction_id']);
});

it('validates original transaction must be an approved payment', function (): void {
    $merchant = Merchant::factory()->create();
    $pendingPayment = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'type' => TransactionType::Payment,
        'status' => TransactionStatus::Pending,
    ]);

    $response = $this->postJson('/api/transactions', [
        'merchant_id' => $merchant->id,
        'idempotency_key' => 'refund-key-2',
        'type' => 'refund',
        'amount' => 50.00,
        'currency' => 'USD',
        'original_transaction_id' => $pendingPayment->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['original_transaction_id']);
});

it('validates original transaction must belong to the same merchant', function (): void {
    $merchant1 = Merchant::factory()->create();
    $merchant2 = Merchant::factory()->create();
    $payment = Transaction::factory()->approved()->create([
        'merchant_id' => $merchant1->id,
        'type' => TransactionType::Payment,
    ]);

    $response = $this->postJson('/api/transactions', [
        'merchant_id' => $merchant2->id,
        'idempotency_key' => 'refund-key-3',
        'type' => 'refund',
        'amount' => 50.00,
        'currency' => 'USD',
        'original_transaction_id' => $payment->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['original_transaction_id']);
});

it('shows a transaction', function (): void {
    $transaction = Transaction::factory()->create();

    $response = $this->getJson("/api/transactions/{$transaction->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $transaction->id);
});

it('lists transactions filtered by merchant', function (): void {
    $merchant = Merchant::factory()->create();
    Transaction::factory()->count(2)->create(['merchant_id' => $merchant->id]);
    Transaction::factory()->create();

    $response = $this->getJson("/api/transactions?merchant_id={$merchant->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists transactions filtered by date range', function (): void {
    $merchant = Merchant::factory()->create();

    Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'created_at' => '2026-01-01 12:00:00',
    ]);
    Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'created_at' => '2026-03-15 12:00:00',
    ]);
    Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'created_at' => '2026-06-01 12:00:00',
    ]);

    $response = $this->getJson('/api/transactions?from=2026-02-01&to=2026-04-01');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});
