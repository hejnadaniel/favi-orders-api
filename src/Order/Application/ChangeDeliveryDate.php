<?php

declare(strict_types=1);

namespace App\Order\Application;

use DateTimeImmutable;

final readonly class ChangeDeliveryDate
{
    public function __construct(
        public string $partnerId,
        public string $orderId,
        public DateTimeImmutable $expectedDeliveryDate,
    ) {
    }
}
