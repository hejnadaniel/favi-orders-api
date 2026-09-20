<?php

declare(strict_types=1);

namespace App\Tests\Order\Application\Double;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Exception\DuplicateOrderException;
use App\Order\Domain\Repository\OrderRepositoryInterface;

final class InMemoryOrderRepository implements OrderRepositoryInterface
{
    public private(set) int $transactionsStarted = 0;

    /** @var array<string, Order> */
    private array $orders = [];

    public function save(Order $order): void
    {
        $key = $this->key($order->partnerId, $order->orderId);

        if (isset($this->orders[$key])) {
            throw new DuplicateOrderException($order->partnerId, $order->orderId);
        }

        $this->orders[$key] = $order;
    }

    public function findByPartnerAndOrderId(string $partnerId, string $orderId): ?Order
    {
        return $this->orders[$this->key($partnerId, $orderId)] ?? null;
    }

    public function wrapInTransaction(callable $work): mixed
    {
        ++$this->transactionsStarted;

        return $work();
    }

    public function count(): int
    {
        return \count($this->orders);
    }

    private function key(string $partnerId, string $orderId): string
    {
        return $partnerId . "\0" . $orderId;
    }
}
