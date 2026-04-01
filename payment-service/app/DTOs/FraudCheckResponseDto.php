<?php

namespace App\DTOs;

readonly class FraudCheckResponseDto
{
    public function __construct(
        public bool $approved,
        public ?string $reason = null,
    ) {}

    /** @param array{approved: bool, reason?: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            approved: $data['approved'],
            reason: $data['reason'] ?? null,
        );
    }
}
