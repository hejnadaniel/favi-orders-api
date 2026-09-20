<?php

declare(strict_types=1);

namespace App\Factory;

use App\Dto\ChangeOrderDeliveryDate;
use App\Dto\Request\PatchOrderRequest;
use App\Service\CalendarDateParser;

final class ChangeOrderDeliveryDateFactory
{
    public function __construct(
        private readonly CalendarDateParser $calendarDateParser,
    ) {
    }

    public function create(string $partnerId, string $orderId, PatchOrderRequest $request): ChangeOrderDeliveryDate
    {
        return new ChangeOrderDeliveryDate(
            partnerId: $partnerId,
            orderId: $orderId,
            expectedDeliveryDate: $this->calendarDateParser->parse($request->expectedDeliveryDate),
        );
    }
}
