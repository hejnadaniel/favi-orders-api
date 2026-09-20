<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Order\Domain\ValueObject\DecimalAmount;
use App\Order\Domain\ValueObject\ProductLine;
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
