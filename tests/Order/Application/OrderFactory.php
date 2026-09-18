<?php

declare(strict_types=1);

namespace App\Tests\Order\Application;

use App\Order\Application\CreateOrder;
use App\Order\Domain\DecimalAmount;
use App\Order\Domain\ProductLine;
use DateTimeImmutable;

final class OrderFactory
{
    /**
     * @param list<ProductLine>|null $products
     */
    public static function createOrder(
        string $partnerId = 'nabytek-brno',
        string $orderId = 'WEB-100001',
        string $expectedDeliveryDate = '2026-10-05',
        string $totalValue = '47940.00',
        ?array $products = null,
    ): CreateOrder {
        return new CreateOrder(
            partnerId: $partnerId,
            orderId: $orderId,
            expectedDeliveryDate: new DateTimeImmutable($expectedDeliveryDate),
            totalValue: DecimalAmount::fromString($totalValue),
            products: $products ?? [self::line()],
        );
    }

    public static function line(
        string $productId = 'SOFA-OSLO-3S',
        string $name = 'Oslo three-seater sofa, grey',
        string $price = '18990.00',
        int $quantity = 2,
    ): ProductLine {
        return new ProductLine($productId, $name, DecimalAmount::fromString($price), $quantity);
    }
}
