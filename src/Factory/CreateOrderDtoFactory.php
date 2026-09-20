<?php

declare(strict_types=1);

namespace App\Factory;

use App\Dto\CreateOrderDto;
use App\Dto\Request\CreateOrderProductRequestDto;
use App\Dto\Request\CreateOrderRequestDto;
use App\Service\CalendarDateParser;
use App\ValueObject\DecimalAmount;
use App\ValueObject\ProductLine;

final class CreateOrderDtoFactory
{
    public function __construct(
        private readonly CalendarDateParser $calendarDateParser,
    ) {
    }

    public function create(string $partnerId, CreateOrderRequestDto $request): CreateOrderDto
    {
        return new CreateOrderDto(
            partnerId: $partnerId,
            orderId: $request->orderId,
            expectedDeliveryDate: $this->calendarDateParser->parse($request->expectedDeliveryDate),
            totalValue: new DecimalAmount($request->totalValue),
            products: array_map($this->createProductLine(...), $request->products),
        );
    }

    private function createProductLine(CreateOrderProductRequestDto $request): ProductLine
    {
        return new ProductLine(
            productId: $request->productId,
            name: $request->name,
            price: new DecimalAmount($request->price),
            quantity: $request->quantity,
        );
    }
}
