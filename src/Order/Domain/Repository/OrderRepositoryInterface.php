<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Exception\DuplicateOrderException;

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
