<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use App\Order\Infrastructure\Http\Validator\ValidDecimalAmount;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $productId,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[ValidDecimalAmount]
        public string $price,

        #[Assert\GreaterThanOrEqual(1)]
        public int $quantity,
    ) {
    }
}
