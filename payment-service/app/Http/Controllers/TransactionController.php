<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Jobs\ProcessFraudCheck;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Transactions
 */
class TransactionController extends Controller
{
    /**
     * List transactions
     *
     * Retrieve transactions with optional filters. Filter by merchant, date range, or both.
     *
     * @queryParam merchant_id string Filter by merchant UUID. Example: 9e4a89f2-1c3b-4d5e-a6f7-8b9c0d1e2f3a
     * @queryParam from string Filter transactions created on or after this date. Example: 2026-01-01
     * @queryParam to string Filter transactions created on or before this date. Example: 2026-12-31
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Transaction::query();

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->input('merchant_id'));
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        return TransactionResource::collection($query->get());
    }

    /**
     * Create transaction
     *
     * Submit a new payment or refund transaction. The transaction is created with a `pending` status
     * and processed asynchronously via a fraud-check service. Use the idempotency key to safely
     * retry requests — duplicate keys return the existing transaction.
     *
     * Poll the show endpoint or configure a merchant webhook to receive the final status
     * (`approved` or `rejected`).
     */
    public function store(StoreTransactionRequest $request): TransactionResource|JsonResponse
    {
        $existing = Transaction::where('idempotency_key', $request->input('idempotency_key'))->first();

        if ($existing) {
            return TransactionResource::make($existing)
                ->response()
                ->setStatusCode(200);
        }

        try {
            $transaction = Transaction::create([
                ...$request->validated(),
                'status' => TransactionStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            $transaction = Transaction::where('idempotency_key', $request->input('idempotency_key'))->firstOrFail();

            return TransactionResource::make($transaction)
                ->response()
                ->setStatusCode(200);
        }

        ProcessFraudCheck::dispatch($transaction)->onQueue('fraud-check');

        /**
         * @status 202
         *
         * @body TransactionResource
         */
        return TransactionResource::make($transaction)
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Get transaction
     *
     * Retrieve a single transaction by ID. Use this to poll for the final status after submission.
     */
    public function show(Transaction $transaction): TransactionResource
    {
        return TransactionResource::make($transaction);
    }
}
