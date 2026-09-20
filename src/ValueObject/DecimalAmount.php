<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Exception\InvalidOrderException;

final readonly class DecimalAmount
{
    public const int PRECISION = 14;
    public const int SCALE = 2;
    public const string PATTERN = '/^\d{1,' . (self::PRECISION - self::SCALE) . '}(\.\d{1,' . self::SCALE . '})?$/';

    public string $value;

    /**
     * @throws InvalidOrderException
     */
    public function __construct(string $amount)
    {
        if (preg_match(self::PATTERN, $amount) !== 1) {
            throw new InvalidOrderException(\sprintf(
                '"%s" is not a non-negative decimal amount that fits numeric(%d, %d).',
                $amount,
                self::PRECISION,
                self::SCALE,
            ));
        }

        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        $this->value = $integer . '.' . str_pad($fraction, self::SCALE, '0');
    }
}
