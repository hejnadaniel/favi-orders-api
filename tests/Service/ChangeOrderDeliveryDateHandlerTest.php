<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\ChangeOrderDeliveryDateDto;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Service\ChangeOrderDeliveryDateHandler;
use App\Service\CreateOrderHandler;
use App\Tests\Dto\OrderDtoFixture;
use App\Tests\Repository\InMemoryOrderRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

use const DATE_ATOM;

final class ChangeOrderDeliveryDateHandlerTest extends TestCase
{
    private InMemoryOrderRepository $orderRepository;
    private MockClock $clock;
    private ChangeOrderDeliveryDateHandler $changeOrderDeliveryDateHandler;
    private Order $existingOrder;

    protected function setUp(): void
    {
        $this->orderRepository = new InMemoryOrderRepository();
        $this->clock = new MockClock('2026-09-21T10:15:00+00:00');
        $this->existingOrder = new CreateOrderHandler($this->orderRepository, $this->clock)
            ->handle(new OrderDtoFixture()->createOrderDto());
        $this->changeOrderDeliveryDateHandler = new ChangeOrderDeliveryDateHandler($this->orderRepository, $this->clock);
    }

    public function testReplacesDeliveryDateAndStampsUpdatedAtFromTheClock(): void
    {
        $this->clock->modify('+1 day');

        $order = $this->changeOrderDeliveryDateHandler->handle($this->dto());

        self::assertSame($this->existingOrder, $order);
        self::assertSame('2026-10-19', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertEquals($this->clock->now(), $order->updatedAt);
        self::assertSame('2026-09-21T10:15:00+00:00', $order->createdAt->format(DATE_ATOM));
    }

    public function testRunsInsideOneTransaction(): void
    {
        $this->changeOrderDeliveryDateHandler->handle($this->dto());

        self::assertSame(1, $this->orderRepository->transactionsStarted);
    }

    public function testIsIdempotentForTheSameDate(): void
    {
        $first = $this->changeOrderDeliveryDateHandler->handle($this->dto());
        $updatedAtAfterFirst = $first->updatedAt;

        $second = $this->changeOrderDeliveryDateHandler->handle($this->dto());

        self::assertSame($first, $second);
        self::assertSame('2026-10-19', $second->expectedDeliveryDate->format('Y-m-d'));
        self::assertEquals($updatedAtAfterFirst, $second->updatedAt);
    }

    public function testThrowsWhenOrderDoesNotExist(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->changeOrderDeliveryDateHandler->handle($this->dto(orderId: 'WEB-UNKNOWN'));
    }

    public function testDoesNotLetAnotherPartnerTouchTheOrder(): void
    {
        try {
            $this->changeOrderDeliveryDateHandler->handle($this->dto(partnerId: 'PRT-2087'));
            self::fail('OrderNotFoundException was not thrown');
        } catch (OrderNotFoundException $exception) {
            self::assertSame('PRT-2087', $exception->partnerId);
            self::assertSame('WEB-100001', $exception->orderId);
        }

        self::assertSame('2026-10-05', $this->existingOrder->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame(0, $this->orderRepository->transactionsStarted, 'a failed lookup must not open a transaction');
    }

    private function dto(string $partnerId = 'PRT-1042', string $orderId = 'WEB-100001'): ChangeOrderDeliveryDateDto
    {
        return new ChangeOrderDeliveryDateDto($partnerId, $orderId, new DateTimeImmutable('2026-10-19'));
    }
}
