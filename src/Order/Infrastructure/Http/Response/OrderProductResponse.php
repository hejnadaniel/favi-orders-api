<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Response;

use App\Order\Domain\OrderProduct;

final readonly class OrderProductResponse
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $price,
        public int $quantity,
    ) {
    }

    public static function fromProduct(OrderProduct $product): self
    {
        return new self($product->productId, $product->name, $product->price, $product->quantity);
    }
}
