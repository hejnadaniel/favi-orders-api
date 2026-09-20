<?php

declare(strict_types=1);

namespace App\Tests\Order\Domain\Entity;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Exception\InvalidOrderException;
use App\Order\Domain\ValueObject\DecimalAmount;
use App\Order\Domain\ValueObject\ProductLine;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class OrderTest extends TestCase
{
    public function testPlacedOrderCarriesEveryValueAsGiven(): void
    {
        $createdAt = new DateTimeImmutable('2026-09-21T10:15:00+00:00');
        $deliveryDate = new DateTimeImmutable('2026-10-05');

        $order = new Order(
            partnerId: 'PRT-1042',
            orderId: 'WEB-100001',
            expectedDeliveryDate: $deliveryDate,
            totalValue: new DecimalAmount('47940.00'),
            products: [
                new ProductLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', new DecimalAmount('18990.00'), 2),
                new ProductLine('CHAIR-VELVET-GRN', 'Velvet dining chair, green', new DecimalAmount('2490.00'), 4),
            ],
            createdAt: $createdAt,
        );

        self::assertInstanceOf(UuidV7::class, $order->id);
        self::assertSame('PRT-1042', $order->partnerId);
        self::assertSame('WEB-100001', $order->orderId);
        self::assertSame($deliveryDate, $order->expectedDeliveryDate);
        self::assertSame('47940.00', $order->totalValue);
        self::assertSame($createdAt, $order->createdAt);
        self::assertSame($createdAt, $order->updatedAt);
    }

    public function testProductsKeepSubmissionOrderAndPointBackToTheOrder(): void
    {
        $order = $this->anyOrder(products: [
            new ProductLine('TABLE-OAK-160', 'Oak dining table 160 cm', new DecimalAmount('12490.00'), 1),
            new ProductLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', new DecimalAmount('18990.00'), 2),
        ]);

        $products = $order->getProducts();

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

        $this->anyOrder(products: []);
    }

    public function testChangingDeliveryDateBumpsUpdatedAtOnly(): void
    {
        $createdAt = new DateTimeImmutable('2026-09-21T10:15:00+00:00');
        $order = $this->anyOrder(createdAt: $createdAt);
        $newDate = new DateTimeImmutable('2026-10-19');
        $changedAt = new DateTimeImmutable('2026-09-22T08:00:00+00:00');

        $order->changeExpectedDeliveryDate($newDate, $changedAt);

        self::assertSame($newDate, $order->expectedDeliveryDate);
        self::assertSame($changedAt, $order->updatedAt);
        self::assertSame($createdAt, $order->createdAt);
    }

    public function testProductLineAcceptsQuantityOfOne(): void
    {
        $productLine = new ProductLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', new DecimalAmount('890.00'), 1);

        self::assertSame(1, $productLine->quantity);
    }

    public function testProductLineRejectsQuantityOfZero(): void
    {
        $this->expectException(InvalidOrderException::class);

        new ProductLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', new DecimalAmount('890.00'), 0);
    }

    /**
     * @param list<ProductLine>|null $products
     */
    private function anyOrder(?array $products = null, ?DateTimeImmutable $createdAt = null): Order
    {
        return new Order(
            partnerId: 'PRT-1042',
            orderId: 'WEB-100001',
            expectedDeliveryDate: new DateTimeImmutable('2026-10-05'),
            totalValue: new DecimalAmount('3290.00'),
            products: $products ?? [new ProductLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', new DecimalAmount('3290.00'), 1)],
            createdAt: $createdAt ?? new DateTimeImmutable('2026-09-21T10:15:00+00:00'),
        );
    }
}
