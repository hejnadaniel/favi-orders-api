<?php

declare(strict_types=1);

namespace App\Tests\Order\Domain;

use App\Order\Domain\DecimalAmount;
use App\Order\Domain\Exception\InvalidOrderException;
use App\Order\Domain\Order;
use App\Order\Domain\ProductLine;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class OrderTest extends TestCase
{
    public function testPlacedOrderCarriesEveryValueAsGiven(): void
    {
        $now = new DateTimeImmutable('2026-06-01T10:15:00+00:00');
        $deliveryDate = new DateTimeImmutable('2026-06-15');

        $order = Order::place(
            partnerId: 'PARTNER_A',
            orderId: 'ORD-001',
            expectedDeliveryDate: $deliveryDate,
            totalValue: DecimalAmount::fromString('1299.99'),
            products: [
                new ProductLine('SKU-001', 'Bluetooth Headphones', DecimalAmount::fromString('129.99'), 2),
                new ProductLine('SKU-002', 'USB-C Cable, 2 m', DecimalAmount::fromString('9.99'), 4),
            ],
            now: $now,
        );

        self::assertInstanceOf(UuidV7::class, $order->id);
        self::assertSame('PARTNER_A', $order->partnerId);
        self::assertSame('ORD-001', $order->orderId);
        self::assertSame($deliveryDate, $order->expectedDeliveryDate);
        self::assertSame('1299.99', $order->totalValue);
        self::assertSame($now, $order->createdAt);
        self::assertSame($now, $order->updatedAt);
    }

    public function testProductsKeepSubmissionOrderAndPointBackToTheOrder(): void
    {
        $order = self::anyOrder(products: [
            new ProductLine('SKU-003', 'Phone Stand', DecimalAmount::fromString('49.99'), 1),
            new ProductLine('SKU-001', 'Bluetooth Headphones', DecimalAmount::fromString('129.99'), 2),
        ]);

        $products = $order->products();

        self::assertCount(2, $products);
        self::assertSame(['SKU-003', 'SKU-001'], array_map(static fn ($product) => $product->productId, $products));
        self::assertSame([0, 1], array_map(static fn ($product) => $product->position, $products));
        self::assertSame('49.99', $products[0]->price);
        self::assertSame(1, $products[0]->quantity);
        self::assertSame($order, $products[0]->order);
        self::assertSame($order, $products[1]->order);
    }

    public function testRejectsOrderWithoutProducts(): void
    {
        $this->expectException(InvalidOrderException::class);

        self::anyOrder(products: []);
    }

    public function testChangingDeliveryDateBumpsUpdatedAtOnly(): void
    {
        $createdAt = new DateTimeImmutable('2026-06-01T10:15:00+00:00');
        $order = self::anyOrder(now: $createdAt);
        $newDate = new DateTimeImmutable('2026-07-20');
        $changedAt = new DateTimeImmutable('2026-06-02T08:00:00+00:00');

        $order->changeExpectedDeliveryDate($newDate, $changedAt);

        self::assertSame($newDate, $order->expectedDeliveryDate);
        self::assertSame($changedAt, $order->updatedAt);
        self::assertSame($createdAt, $order->createdAt);
    }

    public function testProductLineAcceptsQuantityOfOne(): void
    {
        $line = new ProductLine('SKU-001', 'Item', DecimalAmount::fromString('1.00'), 1);

        self::assertSame(1, $line->quantity);
    }

    public function testProductLineRejectsQuantityOfZero(): void
    {
        $this->expectException(InvalidOrderException::class);

        new ProductLine('SKU-001', 'Item', DecimalAmount::fromString('1.00'), 0);
    }

    /**
     * @param list<ProductLine>|null $products
     */
    private static function anyOrder(?array $products = null, ?DateTimeImmutable $now = null): Order
    {
        return Order::place(
            partnerId: 'PARTNER_A',
            orderId: 'ORD-001',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: DecimalAmount::fromString('100.00'),
            products: $products ?? [new ProductLine('SKU-001', 'Item', DecimalAmount::fromString('100.00'), 1)],
            now: $now ?? new DateTimeImmutable('2026-06-01T10:15:00+00:00'),
        );
    }
}
