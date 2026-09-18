<?php

declare(strict_types=1);

namespace App\Tests\Order\Application;

use App\Order\Application\OrderCreator;
use App\Order\Application\OrderFinder;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Tests\Order\Application\Doubles\InMemoryOrderRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OrderFinderTest extends TestCase
{
    private InMemoryOrderRepository $orders;
    private OrderFinder $finder;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository();
        $this->finder = new OrderFinder($this->orders);
    }

    public function testReturnsTheStoredOrder(): void
    {
        $created = new OrderCreator($this->orders, new MockClock())->create(OrderFactory::createOrder());

        self::assertSame($created, $this->finder->get('nabytek-brno', 'WEB-100001'));
    }

    public function testThrowsWhenOrderDoesNotExist(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->finder->get('nabytek-brno', 'WEB-MISSING');
    }
}
