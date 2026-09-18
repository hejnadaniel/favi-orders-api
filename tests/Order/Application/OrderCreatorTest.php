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
        $this->clock = new MockClock('2026-09-21T10:15:00+00:00');
        $this->creator = new OrderCreator($this->orders, $this->clock);
    }

    public function testStoresOrderUnderItsCompositeKeyWithValuesAsSubmitted(): void
    {
        $command = OrderFactory::createOrder(products: [
            OrderFactory::line('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', '18990.00', 2),
            OrderFactory::line('CHAIR-VELVET-GRN', 'Velvet dining chair, green', '2490.00', 4),
        ]);

        $order = $this->creator->create($command);

        self::assertSame($order, $this->orders->find('nabytek-brno', 'WEB-100001'));
        self::assertSame('nabytek-brno', $order->partnerId);
        self::assertSame('WEB-100001', $order->orderId);
        self::assertSame('2026-10-05', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame('47940.00', $order->totalValue);
        self::assertSame(
            ['SOFA-OSLO-3S', 'CHAIR-VELVET-GRN'],
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
        $first = $this->creator->create(OrderFactory::createOrder(totalValue: '3290.00'));

        $this->expectException(DuplicateOrderException::class);

        try {
            $this->creator->create(OrderFactory::createOrder(totalValue: '890.00'));
        } finally {
            self::assertSame(1, $this->orders->count());
            self::assertSame($first, $this->orders->find('nabytek-brno', 'WEB-100001'));
            self::assertSame('3290.00', $first->totalValue);
        }
    }

    public function testSameOrderIdIsAllowedForAnotherPartner(): void
    {
        $this->creator->create(OrderFactory::createOrder(partnerId: 'nabytek-brno'));

        $this->creator->create(OrderFactory::createOrder(partnerId: 'nabytek-ostrava'));

        self::assertSame(2, $this->orders->count());
    }

    public function testDuplicateExceptionNamesTheConflictingKey(): void
    {
        $this->creator->create(OrderFactory::createOrder(partnerId: 'nabytek-brno', orderId: 'WEB-100900'));

        try {
            $this->creator->create(OrderFactory::createOrder(partnerId: 'nabytek-brno', orderId: 'WEB-100900'));
            self::fail('DuplicateOrderException was not thrown');
        } catch (DuplicateOrderException $exception) {
            self::assertSame('nabytek-brno', $exception->partnerId);
            self::assertSame('WEB-100900', $exception->orderId);
            self::assertSame(409, $exception->status());
        }
    }
}
