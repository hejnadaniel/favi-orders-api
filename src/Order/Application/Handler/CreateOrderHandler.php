<?php

declare(strict_types=1);

namespace App\Order\Application\Handler;

use App\Order\Application\Command\CreateOrderCommand;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Exception\DuplicateOrderException;
use App\Order\Domain\Exception\InvalidOrderException;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Clock\ClockInterface;

final class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws DuplicateOrderException
     * @throws InvalidOrderException
     */
    public function handle(CreateOrderCommand $command): Order
    {
        if ($this->orderRepository->findByPartnerAndOrderId($command->partnerId, $command->orderId) !== null) {
            throw new DuplicateOrderException($command->partnerId, $command->orderId);
        }

        $order = new Order(
            partnerId: $command->partnerId,
            orderId: $command->orderId,
            expectedDeliveryDate: $command->expectedDeliveryDate,
            totalValue: $command->totalValue,
            products: $command->products,
            createdAt: $this->clock->now(),
        );

        $this->orderRepository->save($order);

        return $order;
    }
}
