<?php

namespace App\Jobs;

use App\DTOs\FraudCheckRequestDto;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\MerchantBalance;
use App\Models\Transaction;
use App\Services\FraudCheckClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessFraudCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(public Transaction $transaction)
    {
        $this->afterCommit = true;
    }

    public function handle(FraudCheckClient $client): void
    {
        $dto = FraudCheckRequestDto::fromTransaction($this->transaction);
        $result = $client->check($dto);

        DB::transaction(function () use ($result): void {
            if ($result->approved) {
                $balance = MerchantBalance::where('merchant_id', $this->transaction->merchant_id)
                    ->where('currency', $this->transaction->currency)
                    ->lockForUpdate()
                    ->first();

                if ($this->transaction->type === TransactionType::Refund) {
                    if (! $balance || bccomp($balance->balance, (string) $this->transaction->amount, 2) < 0) {
                        $this->transaction->update([
                            'status' => TransactionStatus::Rejected,
                            'fraud_reason' => 'Insufficient merchant balance for refund',
                        ]);

                        return;
                    }

                    $balance->decrement('balance', $this->transaction->amount);
                } else {
                    if (! $balance) {
                        $balance = MerchantBalance::create([
                            'merchant_id' => $this->transaction->merchant_id,
                            'currency' => $this->transaction->currency,
                            'balance' => 0,
                        ]);
                    }

                    $balance->increment('balance', $this->transaction->amount);
                }

                $this->transaction->update(['status' => TransactionStatus::Approved]);
            } else {
                $this->transaction->update([
                    'status' => TransactionStatus::Rejected,
                    'fraud_reason' => $result->reason,
                ]);
            }
        });

        $this->transaction->refresh();
        $this->dispatchWebhookIfNeeded();
    }

    public function failed(Throwable $exception): void
    {
        $this->transaction->update([
            'status' => TransactionStatus::Rejected,
            'fraud_reason' => 'Fraud check service unavailable',
        ]);

        $this->dispatchWebhookIfNeeded();
    }

    private function dispatchWebhookIfNeeded(): void
    {
        $merchant = $this->transaction->merchant;

        if ($merchant->webhook_url) {
            SendWebhook::dispatch($this->transaction)->onQueue('webhook');
        }
    }
}
