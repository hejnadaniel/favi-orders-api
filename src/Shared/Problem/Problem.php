<?php

declare(strict_types=1);

namespace App\Shared\Problem;

/**
 * Contract between application exceptions and the HTTP problem-details layer.
 *
 * Implemented by exceptions that are part of the API contract. The listener
 * building RFC 9457 responses reads these three values and the message; it never
 * needs to know the concrete exception class.
 */
interface Problem
{
    /**
     * Last path segment of the problem `type` URI, e.g. `duplicate-order`.
     */
    public function slug(): string;

    public function status(): int;

    public function title(): string;
}
