<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /**
             * The merchant this transaction belongs to.
             *
             * @example 9e4a89f2-1c3b-4d5e-a6f7-8b9c0d1e2f3a
             */
            'merchant_id' => ['required', 'uuid', 'exists:merchants,id'],
            /**
             * Client-generated unique key to prevent duplicate processing.
             *
             * @example ord_2026-03-31_001
             */
            'idempotency_key' => ['required', 'string'],
            /**
             * Transaction type: `payment` credits the merchant, `refund` debits.
             *
             * @example payment
             */
            'type' => ['required', new Enum(TransactionType::class)],
            /**
             * Amount in the smallest major currency unit (e.g. 99.99).
             *
             * @example 149.99
             */
            'amount' => ['required', 'numeric', 'gt:0'],
            /**
             * ISO 4217 currency code.
             *
             * @example USD
             */
            'currency' => ['required', 'string', 'size:3'],
            /**
             * Required for refunds. Must reference an approved payment belonging to the same merchant.
             *
             * @example null
             */
            'original_transaction_id' => ['required_if:type,refund', 'nullable', 'uuid', 'exists:transactions,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('type') !== TransactionType::Refund->value) {
                    return;
                }

                $original = Transaction::find($this->input('original_transaction_id'));

                if (! $original) {
                    $validator->errors()->add('original_transaction_id', 'The original transaction does not exist.');

                    return;
                }

                if ($original->type !== TransactionType::Payment) {
                    $validator->errors()->add('original_transaction_id', 'The original transaction must be a payment.');

                    return;
                }

                if ($original->status !== TransactionStatus::Approved) {
                    $validator->errors()->add('original_transaction_id', 'The original transaction must be approved.');

                    return;
                }

                if ($original->merchant_id !== $this->input('merchant_id')) {
                    $validator->errors()->add('original_transaction_id', 'The original transaction must belong to the same merchant.');
                }
            },
        ];
    }
}
