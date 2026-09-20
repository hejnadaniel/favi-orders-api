<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use DateTimeImmutable;

final readonly class ChangeOrderDeliveryDateCommand
{
    public function __construct(
        public string $partnerId,
        public string $orderId,
        public DateTimeImmutable $expectedDeliveryDate,
    ) {
    }
}
