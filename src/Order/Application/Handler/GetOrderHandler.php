<?php

declare(strict_types=1);

namespace App\Order\Application\Handler;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Repository\OrderRepositoryInterface;

final class GetOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * @throws OrderNotFoundException
     */
    public function handle(string $partnerId, string $orderId): Order
    {
        return $this->orderRepository->findByPartnerAndOrderId($partnerId, $orderId)
            ?? throw new OrderNotFoundException($partnerId, $orderId);
    }
}
