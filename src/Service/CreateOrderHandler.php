<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateOrder;
use App\Entity\Order;
use App\Exception\DuplicateOrderException;
use App\Exception\InvalidOrderException;
use App\Repository\OrderRepositoryInterface;
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
    public function handle(CreateOrder $dto): Order
    {
        if ($this->orderRepository->findByPartnerAndOrderId($dto->partnerId, $dto->orderId) !== null) {
            throw new DuplicateOrderException($dto->partnerId, $dto->orderId);
        }

        $order = new Order(
            partnerId: $dto->partnerId,
            orderId: $dto->orderId,
            expectedDeliveryDate: $dto->expectedDeliveryDate,
            totalValue: $dto->totalValue,
            products: $dto->products,
            createdAt: $this->clock->now(),
        );

        $this->orderRepository->save($order);

        return $order;
    }
}
