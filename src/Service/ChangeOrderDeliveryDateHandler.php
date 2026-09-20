<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ChangeOrderDeliveryDate;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepositoryInterface;
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
    public function handle(ChangeOrderDeliveryDate $dto): Order
    {
        $order = $this->orderRepository->findByPartnerAndOrderId($dto->partnerId, $dto->orderId)
            ?? throw new OrderNotFoundException($dto->partnerId, $dto->orderId);

        return $this->orderRepository->wrapInTransaction(function () use ($order, $dto): Order {
            $order->changeExpectedDeliveryDate($dto->expectedDeliveryDate, $this->clock->now());

            return $order;
        });
    }
}
