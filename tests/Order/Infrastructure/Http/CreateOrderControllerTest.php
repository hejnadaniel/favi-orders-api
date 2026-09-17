<?php

declare(strict_types=1);

namespace App\Tests\Order\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;

final class CreateOrderControllerTest extends ApiTestCase
{
    public function testCreatesOrderAndPointsToItWithLocation(): void
    {
        $payload = self::orderPayload();

        $this->postOrder($payload);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseHeaderSame('Location', self::orderPath());

        $body = $this->responseBody();
        self::assertSame(self::PARTNER_ID, $body['partnerId']);
        self::assertSame(self::ORDER_ID, $body['orderId']);
        self::assertSame('2026-06-15', $body['expectedDeliveryDate']);
        self::assertSame('1299.99', $body['totalValue']);
        self::assertSame($payload['products'], $body['products']);
        self::assertSame($body['createdAt'], $body['updatedAt']);
    }

    public function testCreatedOrderCanBeReadBackFromTheLocation(): void
    {
        $this->postOrder(self::orderPayload());
        $created = $this->responseBody();

        $this->getOrder();

        self::assertResponseStatusCodeSame(200);
        self::assertSame($created, $this->responseBody());
    }

    public function testAmountsAreNormalisedToTwoFractionalDigits(): void
    {
        $this->postOrder(self::orderPayload([
            'totalValue' => '100',
            'products' => [['productId' => 'SKU-001', 'name' => 'Item', 'price' => '99.9', 'quantity' => 1]],
        ]));

        self::assertResponseStatusCodeSame(201);
        $body = $this->responseBody();
        self::assertSame('100.00', $body['totalValue']);
        self::assertSame([['productId' => 'SKU-001', 'name' => 'Item', 'price' => '99.90', 'quantity' => 1]], $body['products']);
    }

    public function testSecondSubmissionOfTheSameOrderConflictsAndKeepsTheFirst(): void
    {
        $this->postOrder(self::orderPayload(['totalValue' => '100.00']));
        self::assertResponseStatusCodeSame(201);

        $this->postOrder(self::orderPayload(['totalValue' => '999.00']));

        self::assertProblem(409, 'duplicate-order');
        $problem = $this->responseBody();
        self::assertSame('https://api.favi.test/problems/duplicate-order', $problem['type']);
        self::assertSame('Duplicate Order', $problem['title']);
        self::assertSame(409, $problem['status']);
        self::assertSame('/api/v1/partners/PARTNER_A/orders', $problem['instance']);

        $this->getOrder();
        self::assertSame('100.00', $this->responseBody()['totalValue']);
    }

    public function testSameOrderIdUnderAnotherPartnerIsANewOrder(): void
    {
        $this->postOrder(self::orderPayload(), 'PARTNER_A');
        $this->postOrder(self::orderPayload(), 'PARTNER_B');

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Location', self::orderPath(partnerId: 'PARTNER_B'));
    }

    public function testRejectsNegativeTotalValueWithFieldPointer(): void
    {
        $this->postOrder(self::orderPayload(['totalValue' => '-1.00']));

        self::assertProblem(422, 'validation-failed');
        $problem = $this->responseBody();
        self::assertSame('https://api.favi.test/problems/validation-failed', $problem['type']);
        self::assertSame(['/totalValue'], self::errorPointers($problem));

        $this->getOrder();
        self::assertResponseStatusCodeSame(404);
    }

    public function testRejectsInvalidNestedProductWithNestedPointers(): void
    {
        $this->postOrder(self::orderPayload(['products' => [
            ['productId' => 'SKU-001', 'name' => 'Item', 'price' => '10.123', 'quantity' => 0],
        ]]));

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/products/0/price', '/products/0/quantity'], self::errorPointers($this->responseBody()));
    }

    public function testRejectsEmptyProductList(): void
    {
        $this->postOrder(self::orderPayload(['products' => []]));

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/products'], self::errorPointers($this->responseBody()));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidDeliveryDates(): iterable
    {
        yield 'wrong format' => ['15.06.2026'];
        yield 'with time' => ['2026-06-15T10:00:00Z'];
        yield 'overflowing day' => ['2026-02-30'];
        yield 'overflowing month' => ['2026-13-01'];
    }

    #[DataProvider('provideInvalidDeliveryDates')]
    public function testRejectsDeliveryDateThatIsNotACalendarDate(string $date): void
    {
        $this->postOrder(self::orderPayload(['expectedDeliveryDate' => $date]));

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], self::errorPointers($this->responseBody()));
    }

    public function testRejectsMissingRequiredField(): void
    {
        $payload = self::orderPayload();
        unset($payload['orderId']);

        $this->postOrder($payload);

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/orderId'], self::errorPointers($this->responseBody()));
    }

    public function testRejectsMalformedJson(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/partners/PARTNER_A/orders',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"orderId": ',
        );

        self::assertProblem(400, 'malformed-request');
        self::assertSame('https://api.favi.test/problems/malformed-request', $this->responseBody()['type']);
    }

    public function testRejectsNonJsonContentType(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/partners/PARTNER_A/orders',
            server: ['CONTENT_TYPE' => 'text/xml'],
            content: '<order/>',
        );

        self::assertProblem(415, 'unsupported-media-type');
        self::assertSame('https://api.favi.test/problems/unsupported-media-type', $this->responseBody()['type']);
    }
}
