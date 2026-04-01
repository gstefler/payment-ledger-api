<?php

namespace App\Jobs;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(public Transaction $transaction) {}

    public function handle(): void
    {
        $webhookUrl = $this->transaction->merchant->webhook_url;

        Http::post($webhookUrl, TransactionResource::make($this->transaction)->resolve())->throw();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Webhook delivery failed', [
            'transaction_id' => $this->transaction->id,
            'merchant_id' => $this->transaction->merchant_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
