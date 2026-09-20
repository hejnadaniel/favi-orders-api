<?php

declare(strict_types=1);

namespace App\Factory;

use App\Dto\CreateOrder;
use App\Dto\Request\CreateOrderProductRequest;
use App\Dto\Request\CreateOrderRequest;
use App\Service\CalendarDateParser;
use App\ValueObject\DecimalAmount;
use App\ValueObject\ProductLine;

final class CreateOrderFactory
{
    public function __construct(
        private readonly CalendarDateParser $calendarDateParser,
    ) {
    }

    public function create(string $partnerId, CreateOrderRequest $request): CreateOrder
    {
        return new CreateOrder(
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
