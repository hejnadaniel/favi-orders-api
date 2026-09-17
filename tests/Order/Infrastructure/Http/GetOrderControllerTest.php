<?php

declare(strict_types=1);

namespace App\Tests\Order\Infrastructure\Http;

final class GetOrderControllerTest extends ApiTestCase
{
    public function testReturnsTheStoredOrder(): void
    {
        $this->postOrder(self::orderPayload());

        $this->getOrder();

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $body = $this->responseBody();
        self::assertSame(self::ORDER_ID, $body['orderId']);
        self::assertSame(
            ['partnerId', 'orderId', 'expectedDeliveryDate', 'totalValue', 'products', 'createdAt', 'updatedAt'],
            array_keys($body),
        );
    }

    public function testUnknownOrderIsNotFound(): void
    {
        $this->getOrder('ORD-404');

        self::assertProblem(404, 'order-not-found');
        self::assertSame('Order "ORD-404" was not found for partner "PARTNER_A".', $this->responseBody()['detail']);
    }

    public function testUnknownRouteIsAProblemToo(): void
    {
        $this->client->request('GET', '/api/v1/nothing-here', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertProblem(404, 'not-found');
        self::assertSame('https://api.favi.test/problems/not-found', $this->responseBody()['type']);
    }

    public function testWrongMethodAdvertisesTheAllowedOnes(): void
    {
        $this->client->request('DELETE', self::orderPath(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertProblem(405, 'method-not-allowed');
        self::assertResponseHeaderSame('Allow', 'GET, PATCH');
    }
}
