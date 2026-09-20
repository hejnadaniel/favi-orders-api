<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Problem\ProblemInterface;
use RuntimeException;

final class OrderNotFoundException extends RuntimeException implements ProblemInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly string $orderId,
    ) {
        parent::__construct(\sprintf('Order "%s" was not found for partner "%s".', $orderId, $partnerId));
    }

    public function getSlug(): string
    {
        return 'order-not-found';
    }

    public function getStatus(): int
    {
        return 404;
    }

    public function getTitle(): string
    {
        return 'Order Not Found';
    }
}
