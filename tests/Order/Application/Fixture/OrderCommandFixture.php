<?php

declare(strict_types=1);

namespace App\Tests\Order\Application\Fixture;

use App\Order\Application\Command\CreateOrderCommand;
use App\Order\Domain\ValueObject\DecimalAmount;
use App\Order\Domain\ValueObject\ProductLine;
use DateTimeImmutable;

final class OrderCommandFixture
{
    /**
     * @param list<ProductLine>|null $products
     */
    public function createOrderCommand(
        string $partnerId = 'PRT-1042',
        string $orderId = 'WEB-100001',
        string $expectedDeliveryDate = '2026-10-05',
        string $totalValue = '47940.00',
        ?array $products = null,
    ): CreateOrderCommand {
        return new CreateOrderCommand(
            partnerId: $partnerId,
            orderId: $orderId,
            expectedDeliveryDate: new DateTimeImmutable($expectedDeliveryDate),
            totalValue: new DecimalAmount($totalValue),
            products: $products ?? [$this->productLine()],
        );
    }

    public function productLine(
        string $productId = 'SOFA-OSLO-3S',
        string $name = 'Oslo three-seater sofa, grey',
        string $price = '18990.00',
        int $quantity = 2,
    ): ProductLine {
        return new ProductLine($productId, $name, new DecimalAmount($price), $quantity);
    }
}
