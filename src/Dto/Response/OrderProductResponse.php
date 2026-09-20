<?php

declare(strict_types=1);

namespace App\Dto\Response;

final readonly class OrderProductResponse
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $price,
        public int $quantity,
    ) {
    }
}
