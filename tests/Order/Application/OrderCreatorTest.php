<?php

declare(strict_types=1);

namespace App\Tests\Order\Application;

use App\Order\Application\OrderCreator;
use App\Order\Domain\Exception\DuplicateOrderException;
use App\Tests\Order\Application\Doubles\InMemoryOrderRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OrderCreatorTest extends TestCase
{
    private InMemoryOrderRepository $orders;
    private MockClock $clock;
    private OrderCreator $creator;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository();
        $this->clock = new MockClock('2026-06-01T10:15:00+00:00');
        $this->creator = new OrderCreator($this->orders, $this->clock);
    }

    public function testStoresOrderUnderItsCompositeKeyWithValuesAsSubmitted(): void
    {
        $command = OrderFactory::createOrder(products: [
            OrderFactory::line('SKU-001', 'Bluetooth Headphones', '129.99', 2),
            OrderFactory::line('SKU-002', 'USB-C Cable, 2 m', '9.99', 4),
        ]);

        $order = $this->creator->create($command);

        self::assertSame($order, $this->orders->find('PARTNER_A', 'ORD-001'));
        self::assertSame('PARTNER_A', $order->partnerId);
        self::assertSame('ORD-001', $order->orderId);
        self::assertSame('2026-06-15', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame('499.00', $order->totalValue);
        self::assertSame(
            ['SKU-001', 'SKU-002'],
            array_map(static fn ($product) => $product->productId, $order->products()),
        );
    }

    public function testTimestampsComeFromTheClock(): void
    {
        $order = $this->creator->create(OrderFactory::createOrder());

        self::assertEquals($this->clock->now(), $order->createdAt);
        self::assertEquals($this->clock->now(), $order->updatedAt);
    }

    public function testRejectsSecondSubmissionOfTheSameOrderAndKeepsTheFirst(): void
    {
        $first = $this->creator->create(OrderFactory::createOrder(totalValue: '100.00'));

        $this->expectException(DuplicateOrderException::class);

        try {
            $this->creator->create(OrderFactory::createOrder(totalValue: '999.00'));
        } finally {
            self::assertSame(1, $this->orders->count());
            self::assertSame($first, $this->orders->find('PARTNER_A', 'ORD-001'));
            self::assertSame('100.00', $first->totalValue);
        }
    }

    public function testSameOrderIdIsAllowedForAnotherPartner(): void
    {
        $this->creator->create(OrderFactory::createOrder(partnerId: 'PARTNER_A'));

        $this->creator->create(OrderFactory::createOrder(partnerId: 'PARTNER_B'));

        self::assertSame(2, $this->orders->count());
    }

    public function testDuplicateExceptionNamesTheConflictingKey(): void
    {
        $this->creator->create(OrderFactory::createOrder(partnerId: 'PARTNER_A', orderId: 'ORD-DUP'));

        try {
            $this->creator->create(OrderFactory::createOrder(partnerId: 'PARTNER_A', orderId: 'ORD-DUP'));
            self::fail('DuplicateOrderException was not thrown');
        } catch (DuplicateOrderException $exception) {
            self::assertSame('PARTNER_A', $exception->partnerId);
            self::assertSame('ORD-DUP', $exception->orderId);
            self::assertSame(409, $exception->status());
        }
    }
}
