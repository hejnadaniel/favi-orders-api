<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Order\Domain\DecimalAmount;
use InvalidArgumentException;

final class InvalidOrderException extends InvalidArgumentException
{
    public static function noProducts(): self
    {
        return new self('An order must contain at least one product.');
    }

    public static function nonPositiveQuantity(int $quantity): self
    {
        return new self(\sprintf('Product quantity must be at least 1, %d given.', $quantity));
    }

    public static function malformedAmount(string $raw): self
    {
        return new self(\sprintf('"%s" is not a non-negative decimal amount that fits numeric(%d, %d).', $raw, DecimalAmount::PRECISION, DecimalAmount::SCALE));
    }
}
