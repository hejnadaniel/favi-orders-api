<?php

declare(strict_types=1);

namespace App\Order\Domain;

use App\Order\Domain\Exception\InvalidOrderException;

final readonly class DecimalAmount
{
    public const int PRECISION = 14;
    public const int SCALE = 2;
    public const string PATTERN = '/^\d{1,' . (self::PRECISION - self::SCALE) . '}(\.\d{1,' . self::SCALE . '})?$/';

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

        return new self($integer . '.' . str_pad($fraction, self::SCALE, '0'));
    }
}
