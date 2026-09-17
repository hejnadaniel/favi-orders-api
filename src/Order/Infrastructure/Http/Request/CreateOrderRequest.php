<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use App\Order\Domain\DecimalAmount;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderRequest
{
    /**
     * @param list<CreateOrderProductRequest> $products
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $orderId,

        #[Assert\NotBlank]
        #[Assert\Date]
        public string $expectedDeliveryDate,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: DecimalAmount::PATTERN, message: 'This value should be a decimal amount with at most 12 integer and 2 fractional digits.')]
        public string $totalValue,

        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $products,
    ) {
    }
}
