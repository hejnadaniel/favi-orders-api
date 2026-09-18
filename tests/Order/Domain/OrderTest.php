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
        $now = new DateTimeImmutable('2026-09-21T10:15:00+00:00');
        $deliveryDate = new DateTimeImmutable('2026-10-05');

        $order = Order::place(
            partnerId: 'nabytek-brno',
            orderId: 'WEB-100001',
            expectedDeliveryDate: $deliveryDate,
            totalValue: DecimalAmount::fromString('47940.00'),
            products: [
                new ProductLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', DecimalAmount::fromString('18990.00'), 2),
                new ProductLine('CHAIR-VELVET-GRN', 'Velvet dining chair, green', DecimalAmount::fromString('2490.00'), 4),
            ],
            now: $now,
        );

        self::assertInstanceOf(UuidV7::class, $order->id);
        self::assertSame('nabytek-brno', $order->partnerId);
        self::assertSame('WEB-100001', $order->orderId);
        self::assertSame($deliveryDate, $order->expectedDeliveryDate);
        self::assertSame('47940.00', $order->totalValue);
        self::assertSame($now, $order->createdAt);
        self::assertSame($now, $order->updatedAt);
    }

    public function testProductsKeepSubmissionOrderAndPointBackToTheOrder(): void
    {
        $order = self::anyOrder(products: [
            new ProductLine('TABLE-OAK-160', 'Oak dining table 160 cm', DecimalAmount::fromString('12490.00'), 1),
            new ProductLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', DecimalAmount::fromString('18990.00'), 2),
        ]);

        $products = $order->products();

        self::assertCount(2, $products);
        self::assertSame(['TABLE-OAK-160', 'SOFA-OSLO-3S'], array_map(static fn ($product) => $product->productId, $products));
        self::assertSame([0, 1], array_map(static fn ($product) => $product->position, $products));
        self::assertSame('12490.00', $products[0]->price);
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
        $createdAt = new DateTimeImmutable('2026-09-21T10:15:00+00:00');
        $order = self::anyOrder(now: $createdAt);
        $newDate = new DateTimeImmutable('2026-10-19');
        $changedAt = new DateTimeImmutable('2026-09-22T08:00:00+00:00');

        $order->changeExpectedDeliveryDate($newDate, $changedAt);

        self::assertSame($newDate, $order->expectedDeliveryDate);
        self::assertSame($changedAt, $order->updatedAt);
        self::assertSame($createdAt, $order->createdAt);
    }

    public function testProductLineAcceptsQuantityOfOne(): void
    {
        $line = new ProductLine('SOFA-OSLO-3S', 'Nightstand Luna', DecimalAmount::fromString('890.00'), 1);

        self::assertSame(1, $line->quantity);
    }

    public function testProductLineRejectsQuantityOfZero(): void
    {
        $this->expectException(InvalidOrderException::class);

        new ProductLine('SOFA-OSLO-3S', 'Nightstand Luna', DecimalAmount::fromString('890.00'), 0);
    }

    /**
     * @param list<ProductLine>|null $products
     */
    private static function anyOrder(?array $products = null, ?DateTimeImmutable $now = null): Order
    {
        return Order::place(
            partnerId: 'nabytek-brno',
            orderId: 'WEB-100001',
            expectedDeliveryDate: new DateTimeImmutable('2026-10-05'),
            totalValue: DecimalAmount::fromString('3290.00'),
            products: $products ?? [new ProductLine('SOFA-OSLO-3S', 'Nightstand Luna', DecimalAmount::fromString('3290.00'), 1)],
            now: $now ?? new DateTimeImmutable('2026-09-21T10:15:00+00:00'),
        );
    }
}
