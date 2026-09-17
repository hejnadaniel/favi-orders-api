<?php

declare(strict_types=1);

namespace App\Order\Domain;

use App\Order\Domain\Exception\DuplicateOrderException;

/**
 * Persistence contract owned by the domain. Doctrine provides one adapter;
 * tests use an in-memory one.
 */
interface OrderRepository
{
    /**
     * @throws DuplicateOrderException when `(partnerId, orderId)` is already taken
     */
    public function add(Order $order): void;

    public function find(string $partnerId, string $orderId): ?Order;

    /**
     * Runs `$work` inside one database transaction and flushes pending changes
     * to managed entities on success.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed;
}
