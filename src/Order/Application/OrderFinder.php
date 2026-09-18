<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Order;
use App\Order\Domain\OrderRepository;

final class OrderFinder
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {
    }

    /**
     * @throws OrderNotFoundException
     */
    public function get(string $partnerId, string $orderId): Order
    {
        return $this->orders->find($partnerId, $orderId)
            ?? throw new OrderNotFoundException($partnerId, $orderId);
    }
}
