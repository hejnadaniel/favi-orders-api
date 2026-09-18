<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Problem\Problem;
use RuntimeException;
use Throwable;

final class DuplicateOrderException extends RuntimeException implements Problem
{
    public function __construct(
        public readonly string $partnerId,
        public readonly string $orderId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            \sprintf('Order "%s" already exists for partner "%s".', $orderId, $partnerId),
            previous: $previous,
        );
    }

    public function slug(): string
    {
        return 'duplicate-order';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Duplicate Order';
    }
}
