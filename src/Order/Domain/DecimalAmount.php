<?php

declare(strict_types=1);

namespace App\Order\Domain;

use App\Order\Domain\Exception\InvalidOrderException;

/**
 * Non-negative decimal amount with at most 12 integer and 2 fractional digits.
 *
 * Kept as a string end to end: no float ever touches the money path. The value
 * is normalised to exactly two fractional digits so that what the API returns
 * right after creation equals what PostgreSQL returns from `numeric(14, 2)`.
 *
 * Deliberately not a Money type: the assignment carries no currency, so there
 * is nothing to attach one to. See README, "Design decisions".
 */
final readonly class DecimalAmount
{
    public const string PATTERN = '/^\d{1,12}(\.\d{1,2})?$/';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidOrderException
     */
    public static function fromString(string $raw): self
    {
        if (preg_match(self::PATTERN, $raw) !== 1) {
            throw InvalidOrderException::malformedAmount($raw);
        }

        [$integer, $fraction] = array_pad(explode('.', $raw, 2), 2, '');

        return new self($integer . '.' . str_pad($fraction, 2, '0'));
    }
}
