<?php

declare(strict_types=1);

namespace App\Tests\Order\Application\Handler;

use App\Order\Application\Handler\CreateOrderHandler;
use App\Order\Application\Handler\GetOrderHandler;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Tests\Order\Application\Double\InMemoryOrderRepository;
use App\Tests\Order\Application\Fixture\OrderDtoFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GetOrderHandlerTest extends TestCase
{
    private InMemoryOrderRepository $orderRepository;
    private GetOrderHandler $getOrderHandler;

    protected function setUp(): void
    {
        $this->orderRepository = new InMemoryOrderRepository();
        $this->getOrderHandler = new GetOrderHandler($this->orderRepository);
    }

    public function testReturnsTheStoredOrder(): void
    {
        $created = new CreateOrderHandler($this->orderRepository, new MockClock())
            ->handle(new OrderDtoFixture()->createOrderDto());

        self::assertSame($created, $this->getOrderHandler->handle('PRT-1042', 'WEB-100001'));
    }

    public function testThrowsWhenOrderDoesNotExist(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->getOrderHandler->handle('PRT-1042', 'WEB-MISSING');
    }
}
