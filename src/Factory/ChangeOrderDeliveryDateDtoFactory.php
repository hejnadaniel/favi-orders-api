<?php

declare(strict_types=1);

namespace App\Factory;

use App\Dto\ChangeOrderDeliveryDateDto;
use App\Dto\Request\PatchOrderRequestDto;
use App\Service\CalendarDateParser;

final class ChangeOrderDeliveryDateDtoFactory
{
    public function __construct(
        private readonly CalendarDateParser $calendarDateParser,
    ) {
    }

    public function create(string $partnerId, string $orderId, PatchOrderRequestDto $request): ChangeOrderDeliveryDateDto
    {
        return new ChangeOrderDeliveryDateDto(
            partnerId: $partnerId,
            orderId: $orderId,
            expectedDeliveryDate: $this->calendarDateParser->parse($request->expectedDeliveryDate),
        );
    }
}
