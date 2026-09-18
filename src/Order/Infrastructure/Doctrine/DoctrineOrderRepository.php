<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Doctrine;

use App\Order\Domain\Exception\DuplicateOrderException;
use App\Order\Domain\Order;
use App\Order\Domain\OrderRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function add(Order $order): void
    {
        $this->entityManager->persist($order);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateOrderException($order->partnerId, $order->orderId, $exception);
        }
    }

    public function find(string $partnerId, string $orderId): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy([
            'partnerId' => $partnerId,
            'orderId' => $orderId,
        ]);
    }

    public function transactional(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction($work);
    }
}
