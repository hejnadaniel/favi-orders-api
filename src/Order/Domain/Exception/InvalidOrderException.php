<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use InvalidArgumentException;

/**
 * A domain invariant was violated while building an order.
 *
 * Request validation at the HTTP boundary catches these cases first and turns
 * them into 422 responses; this exception is the last line of defence for
 * callers that bypass HTTP.
 */
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
        return new self(\sprintf('"%s" is not a decimal amount with up to 12 integer and 2 fractional digits.', $raw));
    }
}
