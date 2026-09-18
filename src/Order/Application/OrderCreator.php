<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Order\Domain\Exception\DuplicateOrderException;
use App\Order\Domain\Exception\InvalidOrderException;
use App\Order\Domain\Order;
use App\Order\Domain\OrderRepository;
use Psr\Clock\ClockInterface;

final class OrderCreator
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws DuplicateOrderException
     * @throws InvalidOrderException
     */
    public function create(CreateOrder $command): Order
    {
        if ($this->orders->find($command->partnerId, $command->orderId) !== null) {
            throw new DuplicateOrderException($command->partnerId, $command->orderId);
        }

        $order = Order::place(
            partnerId: $command->partnerId,
            orderId: $command->orderId,
            expectedDeliveryDate: $command->expectedDeliveryDate,
            totalValue: $command->totalValue,
            products: $command->products,
            now: $this->clock->now(),
        );

        $this->orders->add($order);

        return $order;
    }
}
