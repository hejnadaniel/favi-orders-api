<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PatchOrderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $expectedDeliveryDate,
    ) {
    }
}
