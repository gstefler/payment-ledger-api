<?php

use App\Jobs\SendWebhook;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('sends a webhook POST to the merchant url', function (): void {
    Http::fake(['*' => Http::response([], 200)]);

    $merchant = Merchant::factory()->withWebhook()->create();
    $transaction = Transaction::factory()->create(['merchant_id' => $merchant->id]);

    (new SendWebhook($transaction))->handle();

    Http::assertSent(function ($request) use ($merchant, $transaction): bool {
        return $request->url() === $merchant->webhook_url
            && $request->data()['id'] === $transaction->id;
    });
});

it('throws on http error for retry', function (): void {
    Http::fake(['*' => Http::response([], 500)]);

    $merchant = Merchant::factory()->withWebhook()->create();
    $transaction = Transaction::factory()->create(['merchant_id' => $merchant->id]);

    expect(fn () => (new SendWebhook($transaction))->handle())
        ->toThrow(RequestException::class);
});

it('logs error when all retries are exhausted', function (): void {
    Log::spy();

    $merchant = Merchant::factory()->withWebhook()->create();
    $transaction = Transaction::factory()->create(['merchant_id' => $merchant->id]);

    (new SendWebhook($transaction))->failed(new RuntimeException('Connection refused'));

    Log::shouldHaveReceived('error');
});
