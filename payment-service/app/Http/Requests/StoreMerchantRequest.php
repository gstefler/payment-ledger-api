<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** @example Acme Corp */
            'name' => ['required', 'string', 'max:255'],
            /** @example merchant@acme.com */
            'email' => ['required', 'email', 'unique:merchants,email'],
            /**
             * URL to receive transaction status webhooks.
             *
             * @example http://webhook-receiver:4000/webhook
             */
            'webhook_url' => ['sometimes', 'nullable', 'url'],
        ];
    }
}
