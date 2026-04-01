<?php

namespace App\Services;

use App\DTOs\FraudCheckRequestDto;
use App\DTOs\FraudCheckResponseDto;
use Illuminate\Support\Facades\Http;

class FraudCheckClient
{
    public function check(FraudCheckRequestDto $request): FraudCheckResponseDto
    {
        $response = Http::post(
            config('services.fraud_check.base_url').'/check',
            $request->toArray(),
        )->throw()->json();

        return FraudCheckResponseDto::fromArray($response);
    }
}
