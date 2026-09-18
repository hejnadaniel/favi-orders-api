<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Problem\Problem;
use RuntimeException;

final class OrderNotFoundException extends RuntimeException implements Problem
{
    public function __construct(
        public readonly string $partnerId,
        public readonly string $orderId,
    ) {
        parent::__construct(\sprintf('Order "%s" was not found for partner "%s".', $orderId, $partnerId));
    }

    public function slug(): string
    {
        return 'order-not-found';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Order Not Found';
    }
}
