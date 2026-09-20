<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ChangeDeliveryDateEndpointTest extends ApiTestCase
{
    public function testReplacesDeliveryDateAndLeavesEverythingElseUntouched(): void
    {
        $this->postOrder($this->orderPayload());
        $created = $this->responseBody();

        $this->putDeliveryDate(['expectedDeliveryDate' => '2026-10-19']);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $body = $this->responseBody();
        self::assertSame('2026-10-19', $body['expectedDeliveryDate']);
        self::assertSame($created['createdAt'], $body['createdAt']);
        self::assertGreaterThanOrEqual($created['updatedAt'], $body['updatedAt']);
        self::assertSame(
            [...$created, 'expectedDeliveryDate' => '2026-10-19', 'updatedAt' => $body['updatedAt']],
            $body,
        );

        $this->getOrder();
        self::assertSame($body, $this->responseBody());
    }

    public function testIsIdempotent(): void
    {
        $this->postOrder($this->orderPayload());

        $this->putDeliveryDate(['expectedDeliveryDate' => '2026-10-19']);
        $first = $this->responseBody();
        $this->putDeliveryDate(['expectedDeliveryDate' => '2026-10-19']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($first['expectedDeliveryDate'], $this->responseBody()['expectedDeliveryDate']);
    }

    public function testUnknownOrderIsNotFound(): void
    {
        $this->putDeliveryDate(['expectedDeliveryDate' => '2026-10-19'], 'WEB-UNKNOWN');

        $this->assertProblem(404, 'order-not-found');
        $problem = $this->responseBody();
        self::assertSame('Order Not Found', $problem['title']);
        self::assertSame($this->deliveryDatePath('WEB-UNKNOWN'), $problem['instance']);
    }

    public function testAnotherPartnerCannotTouchTheOrder(): void
    {
        $this->postOrder($this->orderPayload(), self::PARTNER_ID);

        $this->putDeliveryDate(['expectedDeliveryDate' => '2026-10-19'], self::ORDER_ID, self::OTHER_PARTNER_ID);

        $this->assertProblem(404, 'order-not-found');

        $this->getOrder(partnerId: self::PARTNER_ID);
        self::assertSame('2026-10-05', $this->responseBody()['expectedDeliveryDate']);
    }

    public function testNullDeliveryDateIsRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->putDeliveryDate(['expectedDeliveryDate' => null]);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], $this->errorPointers($this->responseBody()));
    }

    public function testOverflowingCalendarDateIsRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->putDeliveryDate(['expectedDeliveryDate' => '2026-02-30']);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], $this->errorPointers($this->responseBody()));

        $this->getOrder();
        self::assertSame('2026-10-05', $this->responseBody()['expectedDeliveryDate']);
    }

    public function testEmptyBodyIsRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->putDeliveryDate([]);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], $this->errorPointers($this->responseBody()));
    }

    public function testFieldsOutsideTheContractAreRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->putDeliveryDate(['expectedDeliveryDate' => '2026-10-19', 'totalValue' => '5.00']);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/totalValue'], $this->errorPointers($this->responseBody()));

        $this->getOrder();
        $body = $this->responseBody();
        self::assertSame('47940.00', $body['totalValue']);
        self::assertSame('2026-10-05', $body['expectedDeliveryDate']);
    }
}
