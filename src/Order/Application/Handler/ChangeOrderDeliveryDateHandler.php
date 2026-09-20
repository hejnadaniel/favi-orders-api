<?php

declare(strict_types=1);

namespace App\Order\Application\Handler;

use App\Order\Application\Command\ChangeOrderDeliveryDateCommand;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Clock\ClockInterface;

final class ChangeOrderDeliveryDateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws OrderNotFoundException
     */
    public function handle(ChangeOrderDeliveryDateCommand $command): Order
    {
        $order = $this->orderRepository->findByPartnerAndOrderId($command->partnerId, $command->orderId)
            ?? throw new OrderNotFoundException($command->partnerId, $command->orderId);

        return $this->orderRepository->wrapInTransaction(function () use ($order, $command): Order {
            $order->changeExpectedDeliveryDate($command->expectedDeliveryDate, $this->clock->now());

            return $order;
        });
    }
}
