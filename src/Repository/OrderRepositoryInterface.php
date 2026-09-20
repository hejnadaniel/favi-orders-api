<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Exception\DuplicateOrderException;

interface OrderRepositoryInterface
{
    /**
     * @throws DuplicateOrderException when `(partnerId, orderId)` is already taken
     */
    public function save(Order $order): void;

    public function findByPartnerAndOrderId(string $partnerId, string $orderId): ?Order;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function wrapInTransaction(callable $work): mixed;
}
