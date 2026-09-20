<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Factory;

use App\Order\Application\Command\CreateOrderCommand;
use App\Order\Domain\ValueObject\DecimalAmount;
use App\Order\Domain\ValueObject\ProductLine;
use App\Order\Infrastructure\Http\Request\CreateOrderProductRequest;
use App\Order\Infrastructure\Http\Request\CreateOrderRequest;
use App\Shared\Date\CalendarDateParser;

final class CreateOrderCommandFactory
{
    public function __construct(
        private readonly CalendarDateParser $calendarDateParser,
    ) {
    }

    public function create(string $partnerId, CreateOrderRequest $request): CreateOrderCommand
    {
        return new CreateOrderCommand(
            partnerId: $partnerId,
            orderId: $request->orderId,
            expectedDeliveryDate: $this->calendarDateParser->parse($request->expectedDeliveryDate),
            totalValue: new DecimalAmount($request->totalValue),
            products: array_map($this->createProductLine(...), $request->products),
        );
    }

    private function createProductLine(CreateOrderProductRequest $request): ProductLine
    {
        return new ProductLine(
            productId: $request->productId,
            name: $request->name,
            price: new DecimalAmount($request->price),
            quantity: $request->quantity,
        );
    }
}
