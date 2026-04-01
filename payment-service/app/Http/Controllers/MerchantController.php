<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Merchants
 */
class MerchantController extends Controller
{
    /**
     * List merchants
     *
     * Retrieve all registered merchants with their current balances per currency.
     */
    public function index(): AnonymousResourceCollection
    {
        return MerchantResource::collection(Merchant::with('balances')->get());
    }

    /**
     * Create merchant
     *
     * Register a new merchant. The merchant starts with no balances.
     * Optionally provide a webhook URL to receive transaction status updates.
     */
    public function store(StoreMerchantRequest $request): JsonResponse
    {
        $merchant = Merchant::create($request->validated());
        $merchant->load('balances');

        /**
         * @status 201
         *
         * @body MerchantResource
         */
        return MerchantResource::make($merchant)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get merchant
     *
     * Retrieve a single merchant by ID, including their balances per currency.
     */
    public function show(Merchant $merchant): MerchantResource
    {
        $merchant->load('balances');

        return MerchantResource::make($merchant);
    }
}
