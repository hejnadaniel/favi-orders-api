<?php

declare(strict_types=1);

namespace App\Exception;

interface ProblemInterface
{
    public function getSlug(): string;

    public function getStatus(): int;

    public function getTitle(): string;
}
