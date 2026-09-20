<?php

declare(strict_types=1);

namespace App\Dto\Response;

final readonly class OrderResponseDto
{
    /**
     * @param list<OrderProductResponseDto> $products
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
