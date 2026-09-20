<?php

declare(strict_types=1);

namespace App\Dto;

use App\ValueObject\DecimalAmount;
use App\ValueObject\ProductLine;
use DateTimeImmutable;

final readonly class CreateOrderDto
{
    /**
     * @param list<ProductLine> $products
     */
    public function __construct(
        public string $partnerId,
        public string $orderId,
        public DateTimeImmutable $expectedDeliveryDate,
        public DecimalAmount $totalValue,
        public array $products,
    ) {
    }
}
