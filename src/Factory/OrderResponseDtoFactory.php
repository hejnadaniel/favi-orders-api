<?php

declare(strict_types=1);

namespace App\Factory;

use App\Dto\Response\OrderProductResponseDto;
use App\Dto\Response\OrderResponseDto;
use App\Entity\Order;
use App\Entity\OrderProduct;
use DateTimeInterface;

final class OrderResponseDtoFactory
{
    public function create(Order $order): OrderResponseDto
    {
        return new OrderResponseDto(
            partnerId: $order->partnerId,
            orderId: $order->orderId,
            expectedDeliveryDate: $order->expectedDeliveryDate->format('Y-m-d'),
            totalValue: $order->totalValue,
            products: array_map($this->createProduct(...), $order->getProducts()),
            createdAt: $order->createdAt->format(DateTimeInterface::ATOM),
            updatedAt: $order->updatedAt->format(DateTimeInterface::ATOM),
        );
    }

    private function createProduct(OrderProduct $product): OrderProductResponseDto
    {
        return new OrderProductResponseDto(
            productId: $product->productId,
            name: $product->name,
            price: $product->price,
            quantity: $product->quantity,
        );
    }
}
