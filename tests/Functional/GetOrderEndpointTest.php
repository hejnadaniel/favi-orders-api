<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class GetOrderEndpointTest extends ApiTestCase
{
    public function testReturnsTheStoredOrder(): void
    {
        $this->postOrder($this->orderPayload());

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
        $this->getOrder('WEB-MISSING');

        $this->assertProblem(404, 'order-not-found');
        self::assertSame('Order "WEB-MISSING" was not found for partner "PRT-1042".', $this->responseBody()['detail']);
    }

    public function testUnknownRouteIsAProblemToo(): void
    {
        $this->client->request('GET', '/api/v1/nothing-here', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertProblem(404, 'not-found');
    }

    public function testWrongMethodAdvertisesTheAllowedOnes(): void
    {
        $this->client->request('DELETE', $this->orderPath(), server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertProblem(405, 'method-not-allowed');
        self::assertResponseHeaderSame('Allow', 'GET, PATCH');
    }
}
