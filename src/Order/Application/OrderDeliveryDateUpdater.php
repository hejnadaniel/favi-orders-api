<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Order;
use App\Order\Domain\OrderRepository;
use Psr\Clock\ClockInterface;

final class OrderDeliveryDateUpdater
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws OrderNotFoundException
     */
    public function update(ChangeDeliveryDate $command): Order
    {
        $order = $this->orders->find($command->partnerId, $command->orderId)
            ?? throw new OrderNotFoundException($command->partnerId, $command->orderId);

        return $this->orders->transactional(function () use ($order, $command): Order {
            $order->changeExpectedDeliveryDate($command->expectedDeliveryDate, $this->clock->now());

            return $order;
        });
    }
}
