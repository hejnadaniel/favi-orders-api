<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Response;

use App\Order\Domain\Order;
use DateTimeInterface;

final readonly class OrderResponse
{
    /**
     * @param list<OrderProductResponse> $products
     */
    public function __construct(
        public string $partnerId,
        public string $orderId,
        public string $expectedDeliveryDate,
        public string $totalValue,
        public array $products,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromOrder(Order $order): self
    {
        return new self(
            partnerId: $order->partnerId,
            orderId: $order->orderId,
            expectedDeliveryDate: $order->expectedDeliveryDate->format('Y-m-d'),
            totalValue: $order->totalValue,
            products: array_map(OrderProductResponse::fromProduct(...), $order->products()),
            createdAt: $order->createdAt->format(DateTimeInterface::ATOM),
            updatedAt: $order->updatedAt->format(DateTimeInterface::ATOM),
        );
    }
}
