<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use App\Order\Domain\DecimalAmount;
use Attribute;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ValidDecimalAmount extends Compound
{
    /**
     * @param array<string, mixed> $options
     *
     * @return list<\Symfony\Component\Validator\Constraint>
     */
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Regex(
                pattern: DecimalAmount::PATTERN,
                message: \sprintf(
                    'This value should be a non-negative decimal with at most %d integer and %d fractional digits.',
                    DecimalAmount::PRECISION - DecimalAmount::SCALE,
                    DecimalAmount::SCALE,
                ),
            ),
        ];
    }
}
