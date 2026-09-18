<?php

declare(strict_types=1);

namespace App\Shared\Problem;

interface Problem
{
    public function slug(): string;

    public function status(): int;

    public function title(): string;
}
