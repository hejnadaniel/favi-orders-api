<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\OrderNotFoundException;
use App\Service\CreateOrderHandler;
use App\Service\GetOrderHandler;
use App\Tests\Dto\OrderFixture;
use App\Tests\Repository\InMemoryOrderRepository;
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
            ->handle(new OrderFixture()->createOrder());

        self::assertSame($created, $this->getOrderHandler->handle('PRT-1042', 'WEB-100001'));
    }

    public function testThrowsWhenOrderDoesNotExist(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->getOrderHandler->handle('PRT-1042', 'WEB-MISSING');
    }
}
