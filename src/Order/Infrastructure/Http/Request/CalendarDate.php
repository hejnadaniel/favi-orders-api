<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;

/**
 * Converts a request date that already passed `Assert\Date` into the
 * immutable, time-less value the application layer expects.
 */
final class CalendarDate
{
    public static function fromValidated(string $date): DateTimeImmutable
    {
        $calendarDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($calendarDate === false) {
            throw new LogicException(\sprintf('"%s" passed validation but is not a Y-m-d date.', $date));
        }

        return $calendarDate;
    }
}
