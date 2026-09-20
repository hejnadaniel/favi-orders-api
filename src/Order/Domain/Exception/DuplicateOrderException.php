<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Problem\ProblemInterface;
use RuntimeException;
use Throwable;

final class DuplicateOrderException extends RuntimeException implements ProblemInterface
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

    public function getSlug(): string
    {
        return 'duplicate-order';
    }

    public function getStatus(): int
    {
        return 409;
    }

    public function getTitle(): string
    {
        return 'Duplicate Order';
    }
}
