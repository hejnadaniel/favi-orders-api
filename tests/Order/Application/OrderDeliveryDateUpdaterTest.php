<?php

declare(strict_types=1);

namespace App\Tests\Order\Application;

use App\Order\Application\ChangeDeliveryDate;
use App\Order\Application\OrderCreator;
use App\Order\Application\OrderDeliveryDateUpdater;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Order;
use App\Tests\Order\Application\Doubles\InMemoryOrderRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

use const DATE_ATOM;

final class OrderDeliveryDateUpdaterTest extends TestCase
{
    private InMemoryOrderRepository $orders;
    private MockClock $clock;
    private OrderDeliveryDateUpdater $updater;
    private Order $existing;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository();
        $this->clock = new MockClock('2026-06-01T10:15:00+00:00');
        $this->existing = new OrderCreator($this->orders, $this->clock)->create(OrderFactory::createOrder());
        $this->updater = new OrderDeliveryDateUpdater($this->orders, $this->clock);
    }

    public function testReplacesDeliveryDateAndStampsUpdatedAtFromTheClock(): void
    {
        $this->clock->modify('+1 day');

        $order = $this->updater->update(new ChangeDeliveryDate('PARTNER_A', 'ORD-001', new DateTimeImmutable('2026-07-20')));

        self::assertSame($this->existing, $order);
        self::assertSame('2026-07-20', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertEquals($this->clock->now(), $order->updatedAt);
        self::assertSame('2026-06-01T10:15:00+00:00', $order->createdAt->format(DATE_ATOM));
    }

    public function testRunsInsideOneTransaction(): void
    {
        $this->updater->update(new ChangeDeliveryDate('PARTNER_A', 'ORD-001', new DateTimeImmutable('2026-07-20')));

        self::assertSame(1, $this->orders->transactionsStarted);
    }

    public function testIsIdempotentForTheSameDate(): void
    {
        $command = new ChangeDeliveryDate('PARTNER_A', 'ORD-001', new DateTimeImmutable('2026-07-20'));

        $first = $this->updater->update($command);
        $updatedAtAfterFirst = $first->updatedAt;
        $second = $this->updater->update($command);

        self::assertSame($first, $second);
        self::assertSame('2026-07-20', $second->expectedDeliveryDate->format('Y-m-d'));
        self::assertEquals($updatedAtAfterFirst, $second->updatedAt);
    }

    public function testThrowsWhenOrderDoesNotExist(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->updater->update(new ChangeDeliveryDate('PARTNER_A', 'NO-SUCH-ORDER', new DateTimeImmutable('2026-07-20')));
    }

    public function testDoesNotLetAnotherPartnerTouchTheOrder(): void
    {
        try {
            $this->updater->update(new ChangeDeliveryDate('PARTNER_B', 'ORD-001', new DateTimeImmutable('2026-07-20')));
            self::fail('OrderNotFoundException was not thrown');
        } catch (OrderNotFoundException $exception) {
            self::assertSame('PARTNER_B', $exception->partnerId);
            self::assertSame('ORD-001', $exception->orderId);
        }

        self::assertSame('2026-06-15', $this->existing->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame(0, $this->orders->transactionsStarted, 'a failed lookup must not open a transaction');
    }
}
