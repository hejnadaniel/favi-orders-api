<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;

final readonly class ChangeOrderDeliveryDateDto
{
    public function __construct(
        public string $partnerId,
        public string $orderId,
        public DateTimeImmutable $expectedDeliveryDate,
    ) {
    }
}
