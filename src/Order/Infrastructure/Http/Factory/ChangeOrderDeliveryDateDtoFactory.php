<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Factory;

use App\Order\Application\Dto\ChangeOrderDeliveryDateDto;
use App\Order\Infrastructure\Http\Request\PatchOrderRequestDto;
use App\Shared\Date\CalendarDateParser;

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
