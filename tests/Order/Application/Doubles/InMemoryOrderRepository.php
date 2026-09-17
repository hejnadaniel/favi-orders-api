<?php

declare(strict_types=1);

namespace App\Tests\Order\Application\Doubles;

use App\Order\Domain\Exception\DuplicateOrderException;
use App\Order\Domain\Order;
use App\Order\Domain\OrderRepository;

/**
 * Working in-memory implementation of the domain repository contract.
 *
 * Mirrors the Doctrine adapter's observable behaviour: `add()` throws on a
 * taken composite key, `find()` returns null when missing. Also records how
 * many transactions were opened so use cases can prove they run inside one.
 */
final class InMemoryOrderRepository implements OrderRepository
{
    public private(set) int $transactionsStarted = 0;

    /** @var array<string, Order> */
    private array $orders = [];

    public function add(Order $order): void
    {
        $key = self::key($order->partnerId, $order->orderId);

        if (isset($this->orders[$key])) {
            throw new DuplicateOrderException($order->partnerId, $order->orderId);
        }

        $this->orders[$key] = $order;
    }

    public function find(string $partnerId, string $orderId): ?Order
    {
        return $this->orders[self::key($partnerId, $orderId)] ?? null;
    }

    public function transactional(callable $work): mixed
    {
        ++$this->transactionsStarted;

        return $work();
    }

    public function count(): int
    {
        return \count($this->orders);
    }

    private static function key(string $partnerId, string $orderId): string
    {
        return $partnerId . "\0" . $orderId;
    }
}
