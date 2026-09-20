<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeOrderDeliveryDateRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $expectedDeliveryDate,
    ) {
    }
}
