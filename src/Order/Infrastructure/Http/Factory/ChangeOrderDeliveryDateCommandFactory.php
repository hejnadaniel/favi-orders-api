<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Factory;

use App\Order\Application\Command\ChangeOrderDeliveryDateCommand;
use App\Order\Infrastructure\Http\Request\PatchOrderRequest;
use App\Shared\Date\CalendarDateParser;

final class ChangeOrderDeliveryDateCommandFactory
{
    public function __construct(
        private readonly CalendarDateParser $calendarDateParser,
    ) {
    }

    public function create(string $partnerId, string $orderId, PatchOrderRequest $request): ChangeOrderDeliveryDateCommand
    {
        return new ChangeOrderDeliveryDateCommand(
            partnerId: $partnerId,
            orderId: $orderId,
            expectedDeliveryDate: $this->calendarDateParser->parse($request->expectedDeliveryDate),
        );
    }
}
