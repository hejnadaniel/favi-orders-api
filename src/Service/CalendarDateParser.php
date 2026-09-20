<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CalendarDateParser
{
    private const string FORMAT = '!Y-m-d';

    /**
     * @throws InvalidArgumentException
     */
    public function parse(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat(self::FORMAT, $date, new DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a calendar date in Y-m-d format.', $date));
        }

        return $parsed;
    }
}
