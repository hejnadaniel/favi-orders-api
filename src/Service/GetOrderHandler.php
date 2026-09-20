<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepositoryInterface;

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
