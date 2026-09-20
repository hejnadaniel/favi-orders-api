<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Validator\Constraints\ValidDecimalAmount;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderProductRequest
{
    public const int MAX_QUANTITY = 1_000_000;

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $productId,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[ValidDecimalAmount]
        public string $price,

        #[Assert\Range(min: 1, max: self::MAX_QUANTITY)]
        public int $quantity,
    ) {
    }
}
