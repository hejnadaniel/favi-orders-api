<?php

declare(strict_types=1);

namespace App\Tests\Order\Application\Handler;

use App\Order\Application\Handler\CreateOrderHandler;
use App\Order\Domain\Exception\DuplicateOrderException;
use App\Tests\Order\Application\Double\InMemoryOrderRepository;
use App\Tests\Order\Application\Fixture\OrderDtoFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateOrderHandlerTest extends TestCase
{
    private InMemoryOrderRepository $orderRepository;
    private MockClock $clock;
    private OrderDtoFixture $dtoFixture;
    private CreateOrderHandler $createOrderHandler;

    protected function setUp(): void
    {
        $this->orderRepository = new InMemoryOrderRepository();
        $this->clock = new MockClock('2026-09-21T10:15:00+00:00');
        $this->dtoFixture = new OrderDtoFixture();
        $this->createOrderHandler = new CreateOrderHandler($this->orderRepository, $this->clock);
    }

    public function testStoresOrderUnderItsCompositeKeyWithValuesAsSubmitted(): void
    {
        $dto = $this->dtoFixture->createOrderDto(products: [
            $this->dtoFixture->productLine('SOFA-OSLO-3S', 'Oslo three-seater sofa, grey', '18990.00', 2),
            $this->dtoFixture->productLine('CHAIR-VELVET-GRN', 'Velvet dining chair, green', '2490.00', 4),
        ]);

        $order = $this->createOrderHandler->handle($dto);

        self::assertSame($order, $this->orderRepository->findByPartnerAndOrderId('PRT-1042', 'WEB-100001'));
        self::assertSame('PRT-1042', $order->partnerId);
        self::assertSame('WEB-100001', $order->orderId);
        self::assertSame('2026-10-05', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame('47940.00', $order->totalValue);
        self::assertSame(
            ['SOFA-OSLO-3S', 'CHAIR-VELVET-GRN'],
            array_map(static fn ($product) => $product->productId, $order->getProducts()),
        );
    }

    public function testTimestampsComeFromTheClock(): void
    {
        $order = $this->createOrderHandler->handle($this->dtoFixture->createOrderDto());

        self::assertEquals($this->clock->now(), $order->createdAt);
        self::assertEquals($this->clock->now(), $order->updatedAt);
    }

    public function testRejectsSecondSubmissionOfTheSameOrderAndKeepsTheFirst(): void
    {
        $first = $this->createOrderHandler->handle($this->dtoFixture->createOrderDto(totalValue: '3290.00'));

        $this->expectException(DuplicateOrderException::class);

        try {
            $this->createOrderHandler->handle($this->dtoFixture->createOrderDto(totalValue: '1.00'));
        } finally {
            self::assertSame(1, $this->orderRepository->count());
            self::assertSame($first, $this->orderRepository->findByPartnerAndOrderId('PRT-1042', 'WEB-100001'));
            self::assertSame('3290.00', $first->totalValue);
        }
    }

    public function testSameOrderIdIsAllowedForAnotherPartner(): void
    {
        $this->createOrderHandler->handle($this->dtoFixture->createOrderDto(partnerId: 'PRT-1042'));

        $this->createOrderHandler->handle($this->dtoFixture->createOrderDto(partnerId: 'PRT-2087'));

        self::assertSame(2, $this->orderRepository->count());
    }

    public function testDuplicateExceptionNamesTheConflictingKey(): void
    {
        $this->createOrderHandler->handle($this->dtoFixture->createOrderDto(orderId: 'WEB-100900'));

        try {
            $this->createOrderHandler->handle($this->dtoFixture->createOrderDto(orderId: 'WEB-100900'));
            self::fail('DuplicateOrderException was not thrown');
        } catch (DuplicateOrderException $exception) {
            self::assertSame('PRT-1042', $exception->partnerId);
            self::assertSame('WEB-100900', $exception->orderId);
            self::assertSame(409, $exception->getStatus());
        }
    }
}
