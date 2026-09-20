<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Exception\DuplicateOrderException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Order $order): void
    {
        $this->entityManager->persist($order);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateOrderException($order->partnerId, $order->orderId, $exception);
        }
    }

    public function findByPartnerAndOrderId(string $partnerId, string $orderId): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy([
            'partnerId' => $partnerId,
            'orderId' => $orderId,
        ]);
    }

    public function wrapInTransaction(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction($work);
    }
}
