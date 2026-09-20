<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use App\Order\Domain\Exception\InvalidOrderException;

final readonly class ProductLine
{
    /**
     * @throws InvalidOrderException
     */
    public function __construct(
        public string $productId,
        public string $name,
        public DecimalAmount $price,
        public int $quantity,
    ) {
        if ($quantity < 1) {
            throw new InvalidOrderException(\sprintf('Product quantity must be at least 1, %d given.', $quantity));
        }
    }
}
