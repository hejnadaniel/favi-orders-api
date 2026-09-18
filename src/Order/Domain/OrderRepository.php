<?php

declare(strict_types=1);

namespace App\Order\Domain;

use App\Order\Domain\Exception\DuplicateOrderException;

interface OrderRepository
{
    /**
     * @throws DuplicateOrderException when `(partnerId, orderId)` is already taken
     */
    public function add(Order $order): void;

    public function find(string $partnerId, string $orderId): ?Order;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed;
}
