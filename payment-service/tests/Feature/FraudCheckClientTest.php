<?php

use App\DTOs\FraudCheckRequestDto;
use App\Services\FraudCheckClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('sends correct request payload to fraud check service', function (): void {
    Http::fake(['*/check' => Http::response(['approved' => true])]);

    config(['services.fraud_check.base_url' => 'http://fraud-service:3000']);

    $dto = new FraudCheckRequestDto(
        transactionId: '550e8400-e29b-41d4-a716-446655440000',
        merchantId: '550e8400-e29b-41d4-a716-446655440001',
        type: 'payment',
        amount: 100.50,
        currency: 'USD',
        idempotencyKey: 'test-key',
    );

    (new FraudCheckClient)->check($dto);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://fraud-service:3000/check'
            && $request->data()['transaction_id'] === '550e8400-e29b-41d4-a716-446655440000'
            && $request->data()['amount'] === 100.50
            && $request->data()['type'] === 'payment';
    });
});

it('returns approved response dto', function (): void {
    Http::fake(['*/check' => Http::response(['approved' => true])]);

    $dto = new FraudCheckRequestDto('id', 'mid', 'payment', 100, 'USD', 'key');

    $result = (new FraudCheckClient)->check($dto);

    expect($result->approved)->toBeTrue()
        ->and($result->reason)->toBeNull();
});

it('returns rejected response dto with reason', function (): void {
    Http::fake(['*/check' => Http::response([
        'approved' => false,
        'reason' => 'Amount too high',
    ])]);

    $dto = new FraudCheckRequestDto('id', 'mid', 'payment', 100, 'USD', 'key');

    $result = (new FraudCheckClient)->check($dto);

    expect($result->approved)->toBeFalse()
        ->and($result->reason)->toBe('Amount too high');
});

it('throws on http error', function (): void {
    Http::fake(['*/check' => Http::response([], 500)]);

    $dto = new FraudCheckRequestDto('id', 'mid', 'payment', 100, 'USD', 'key');

    expect(fn () => (new FraudCheckClient)->check($dto))
        ->toThrow(RequestException::class);
});
