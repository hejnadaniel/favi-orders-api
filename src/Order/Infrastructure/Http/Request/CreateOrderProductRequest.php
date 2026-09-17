<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use App\Order\Domain\DecimalAmount;
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

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: DecimalAmount::PATTERN, message: 'This value should be a decimal amount with at most 12 integer and 2 fractional digits.')]
        public string $price,

        #[Assert\GreaterThanOrEqual(1)]
        public int $quantity,
    ) {
    }
}
