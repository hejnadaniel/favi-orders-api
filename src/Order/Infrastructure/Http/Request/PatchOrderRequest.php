<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON Merge Patch document for an order. Only the expected delivery date is
 * patchable in v1, so the field is required; the next patchable field becomes
 * a nullable property here without a new endpoint.
 *
 * The date stays a string until validation has run: `Assert\Date` rejects
 * overflowing values such as `2026-02-30`, which `createFromFormat()` would
 * silently roll over.
 */
final readonly class PatchOrderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $expectedDeliveryDate,
    ) {
    }
}
