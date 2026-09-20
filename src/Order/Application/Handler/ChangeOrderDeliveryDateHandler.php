<?php

declare(strict_types=1);

namespace App\Order\Application\Handler;

use App\Order\Application\Dto\ChangeOrderDeliveryDateDto;
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
    public function handle(ChangeOrderDeliveryDateDto $dto): Order
    {
        $order = $this->orderRepository->findByPartnerAndOrderId($dto->partnerId, $dto->orderId)
            ?? throw new OrderNotFoundException($dto->partnerId, $dto->orderId);

        return $this->orderRepository->wrapInTransaction(function () use ($order, $dto): Order {
            $order->changeExpectedDeliveryDate($dto->expectedDeliveryDate, $this->clock->now());

            return $order;
        });
    }
}
