<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Validator\Constraints\ValidDecimalAmount;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderProductRequestDto
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
