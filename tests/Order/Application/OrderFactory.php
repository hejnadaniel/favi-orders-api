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
        string $partnerId = 'PARTNER_A',
        string $orderId = 'ORD-001',
        string $expectedDeliveryDate = '2026-06-15',
        string $totalValue = '499.00',
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
        string $productId = 'SKU-001',
        string $name = 'Bluetooth Headphones',
        string $price = '129.99',
        int $quantity = 2,
    ): ProductLine {
        return new ProductLine($productId, $name, DecimalAmount::fromString($price), $quantity);
    }
}
