<?php

use App\DTOs\FraudCheckResponseDto;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\ProcessFraudCheck;
use App\Jobs\SendWebhook;
use App\Models\Merchant;
use App\Models\MerchantBalance;
use App\Models\Transaction;
use App\Services\FraudCheckClient;
use Illuminate\Support\Facades\Queue;

it('approves a payment and increments merchant balance for the currency', function (): void {
    $merchant = Merchant::factory()->create();
    MerchantBalance::factory()->create(['merchant_id' => $merchant->id, 'currency' => 'USD', 'balance' => 100]);

    $transaction = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'amount' => 50,
        'currency' => 'USD',
        'type' => TransactionType::Payment,
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(new FraudCheckResponseDto(approved: true));

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    $transaction->refresh();
    $balance = MerchantBalance::where('merchant_id', $merchant->id)->where('currency', 'USD')->first();

    expect($transaction->status)->toBe(TransactionStatus::Approved)
        ->and($balance->balance)->toBe('150.00');
});

it('creates a new balance row for a new currency on payment', function (): void {
    $merchant = Merchant::factory()->create();

    $transaction = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'amount' => 75,
        'currency' => 'EUR',
        'type' => TransactionType::Payment,
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(new FraudCheckResponseDto(approved: true));

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    $transaction->refresh();
    $balance = MerchantBalance::where('merchant_id', $merchant->id)->where('currency', 'EUR')->first();

    expect($transaction->status)->toBe(TransactionStatus::Approved)
        ->and($balance)->not->toBeNull()
        ->and($balance->balance)->toBe('75.00');
});

it('approves a refund and decrements merchant balance for the currency', function (): void {
    $merchant = Merchant::factory()->create();
    MerchantBalance::factory()->create(['merchant_id' => $merchant->id, 'currency' => 'USD', 'balance' => 200]);

    $original = Transaction::factory()->approved()->create([
        'merchant_id' => $merchant->id,
        'type' => TransactionType::Payment,
        'amount' => 200,
        'currency' => 'USD',
    ]);
    $transaction = Transaction::factory()->refund($original)->create([
        'merchant_id' => $merchant->id,
        'amount' => 75,
        'currency' => 'USD',
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(new FraudCheckResponseDto(approved: true));

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    $transaction->refresh();
    $balance = MerchantBalance::where('merchant_id', $merchant->id)->where('currency', 'USD')->first();

    expect($transaction->status)->toBe(TransactionStatus::Approved)
        ->and($balance->balance)->toBe('125.00');
});

it('rejects a refund when merchant balance is insufficient for the currency', function (): void {
    $merchant = Merchant::factory()->create();
    MerchantBalance::factory()->create(['merchant_id' => $merchant->id, 'currency' => 'USD', 'balance' => 30]);

    $original = Transaction::factory()->approved()->create([
        'merchant_id' => $merchant->id,
        'type' => TransactionType::Payment,
        'amount' => 100,
        'currency' => 'USD',
    ]);
    $transaction = Transaction::factory()->refund($original)->create([
        'merchant_id' => $merchant->id,
        'amount' => 50,
        'currency' => 'USD',
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(new FraudCheckResponseDto(approved: true));

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    $transaction->refresh();
    $balance = MerchantBalance::where('merchant_id', $merchant->id)->where('currency', 'USD')->first();

    expect($transaction->status)->toBe(TransactionStatus::Rejected)
        ->and($transaction->fraud_reason)->toBe('Insufficient merchant balance for refund')
        ->and($balance->balance)->toBe('30.00');
});

it('rejects a refund when no balance exists for the currency', function (): void {
    $merchant = Merchant::factory()->create();
    MerchantBalance::factory()->create(['merchant_id' => $merchant->id, 'currency' => 'USD', 'balance' => 500]);

    $original = Transaction::factory()->approved()->create([
        'merchant_id' => $merchant->id,
        'type' => TransactionType::Payment,
        'amount' => 100,
        'currency' => 'EUR',
    ]);
    $transaction = Transaction::factory()->refund($original)->create([
        'merchant_id' => $merchant->id,
        'amount' => 50,
        'currency' => 'EUR',
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(new FraudCheckResponseDto(approved: true));

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    $transaction->refresh();

    expect($transaction->status)->toBe(TransactionStatus::Rejected)
        ->and($transaction->fraud_reason)->toBe('Insufficient merchant balance for refund');
});

it('rejects a transaction when fraud check fails', function (): void {
    $merchant = Merchant::factory()->create();
    $transaction = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'type' => TransactionType::Payment,
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(
        new FraudCheckResponseDto(approved: false, reason: 'Amount too high')
    );

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    $transaction->refresh();

    expect($transaction->status)->toBe(TransactionStatus::Rejected)
        ->and($transaction->fraud_reason)->toBe('Amount too high');
});

it('marks transaction as rejected when all retries are exhausted', function (): void {
    $merchant = Merchant::factory()->create();
    $transaction = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
    ]);

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->failed(new RuntimeException('Connection refused'));

    $transaction->refresh();

    expect($transaction->status)->toBe(TransactionStatus::Rejected)
        ->and($transaction->fraud_reason)->toBe('Fraud check service unavailable');
});

it('dispatches webhook when merchant has webhook url', function (): void {
    $merchant = Merchant::factory()->withWebhook()->create();
    $transaction = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'type' => TransactionType::Payment,
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(new FraudCheckResponseDto(approved: true));

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    Queue::assertPushedOn('webhook', SendWebhook::class);
});

it('does not dispatch webhook when merchant has no webhook url', function (): void {
    $merchant = Merchant::factory()->create(['webhook_url' => null]);
    $transaction = Transaction::factory()->create([
        'merchant_id' => $merchant->id,
        'type' => TransactionType::Payment,
    ]);

    $client = Mockery::mock(FraudCheckClient::class);
    $client->shouldReceive('check')->once()->andReturn(new FraudCheckResponseDto(approved: true));

    Queue::fake(SendWebhook::class);

    (new ProcessFraudCheck($transaction))->handle($client);

    Queue::assertNotPushed(SendWebhook::class);
});
