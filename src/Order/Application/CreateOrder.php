<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Order\Domain\DecimalAmount;
use App\Order\Domain\ProductLine;
use DateTimeImmutable;

final readonly class CreateOrder
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
