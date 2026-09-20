<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Response;

final readonly class OrderResponse
{
    /**
     * @param list<OrderProductResponse> $products
     */
    public function __construct(
        public string $partnerId,
        public string $orderId,
        public string $expectedDeliveryDate,
        public string $totalValue,
        public array $products,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
