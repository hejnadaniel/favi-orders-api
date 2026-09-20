<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Request;

use App\Order\Infrastructure\Http\Validator\ValidDecimalAmount;
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

        #[ValidDecimalAmount]
        public string $totalValue,

        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $products,
    ) {
    }
}
