<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Dto\Request\CreateOrderRequest;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateOrderEndpointTest extends ApiTestCase
{
    public function testCreatesOrderAndPointsToItWithLocation(): void
    {
        $payload = $this->orderPayload();

        $this->postOrder($payload);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseHeaderSame('Location', $this->orderPath());

        $body = $this->responseBody();
        self::assertSame(self::PARTNER_ID, $body['partnerId']);
        self::assertSame(self::ORDER_ID, $body['orderId']);
        self::assertSame('2026-10-05', $body['expectedDeliveryDate']);
        self::assertSame('47940.00', $body['totalValue']);
        self::assertSame($payload['products'], $body['products']);
        self::assertSame($body['createdAt'], $body['updatedAt']);
    }

    public function testCreatedOrderCanBeReadBackFromTheLocation(): void
    {
        $this->postOrder($this->orderPayload());
        $created = $this->responseBody();

        $this->getOrder();

        self::assertResponseStatusCodeSame(200);
        self::assertSame($created, $this->responseBody());
    }

    public function testAmountsAreNormalisedToTwoFractionalDigits(): void
    {
        $this->postOrder($this->orderPayload([
            'totalValue' => '350',
            'products' => [['productId' => 'SOFA-OSLO-3S', 'name' => 'Nightstand Luna', 'price' => '99.9', 'quantity' => 1]],
        ]));

        self::assertResponseStatusCodeSame(201);
        $body = $this->responseBody();
        self::assertSame('350.00', $body['totalValue']);
        self::assertSame([['productId' => 'SOFA-OSLO-3S', 'name' => 'Nightstand Luna', 'price' => '99.90', 'quantity' => 1]], $body['products']);
    }

    public function testSecondSubmissionOfTheSameOrderConflictsAndKeepsTheFirst(): void
    {
        $this->postOrder($this->orderPayload(['totalValue' => '3290.00']));
        self::assertResponseStatusCodeSame(201);

        $this->postOrder($this->orderPayload(['totalValue' => '890.00']));

        $this->assertProblem(409, 'duplicate-order');
        $problem = $this->responseBody();
        self::assertSame('Duplicate Order', $problem['title']);
        self::assertSame('/api/v1/partners/PRT-1042/orders', $problem['instance']);

        $this->getOrder();
        self::assertSame('3290.00', $this->responseBody()['totalValue']);
    }

    public function testSameOrderIdUnderAnotherPartnerIsANewOrder(): void
    {
        $this->postOrder($this->orderPayload(), 'PRT-1042');
        $this->postOrder($this->orderPayload(), self::OTHER_PARTNER_ID);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Location', $this->orderPath(partnerId: self::OTHER_PARTNER_ID));
    }

    public function testAcceptsIdentifiersAtTheLengthLimitAndWithNonAsciiCharacters(): void
    {
        $partnerId = str_repeat('ž', 64);
        $orderId = 'objednávka č. 17';

        $this->postOrder($this->orderPayload(['orderId' => $orderId]), rawurlencode($partnerId));

        self::assertResponseStatusCodeSame(201);
        $body = $this->responseBody();
        self::assertSame($partnerId, $body['partnerId']);
        self::assertSame($orderId, $body['orderId']);

        $this->getOrder(rawurlencode($orderId), rawurlencode($partnerId));
        self::assertResponseStatusCodeSame(200);
    }

    public function testPartnerIdOnePastTheLengthLimitMatchesNoRoute(): void
    {
        $this->postOrder($this->orderPayload(), str_repeat('p', 65));

        $this->assertProblem(404, 'not-found');
    }

    public function testOrderIdOnePastTheLengthLimitIsAValidationError(): void
    {
        $this->postOrder($this->orderPayload(['orderId' => str_repeat('o', 65)]));

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/orderId'], $this->errorPointers($this->responseBody()));
    }

    public function testRejectsNegativeTotalValueWithFieldPointer(): void
    {
        $this->postOrder($this->orderPayload(['totalValue' => '-250.00']));

        $this->assertProblem(422, 'validation-failed');
        $problem = $this->responseBody();
        self::assertSame(['/totalValue'], $this->errorPointers($problem));

        $this->getOrder();
        self::assertResponseStatusCodeSame(404);
    }

    public function testRejectsInvalidNestedProductWithNestedPointers(): void
    {
        $this->postOrder($this->orderPayload(['products' => [
            ['productId' => 'SOFA-OSLO-3S', 'name' => 'Nightstand Luna', 'price' => '10.123', 'quantity' => 0],
        ]]));

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/products/0/price', '/products/0/quantity'], $this->errorPointers($this->responseBody()));
    }

    public function testRejectsQuantityAboveTheAllowedMaximum(): void
    {
        $this->postOrder($this->orderPayload(['products' => [
            ['productId' => 'SOFA-OSLO-3S', 'name' => 'Oslo three-seater sofa, grey', 'price' => '18990.00', 'quantity' => 5_000_000_000],
        ]]));

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/products/0/quantity'], $this->errorPointers($this->responseBody()));
    }

    public function testRejectsMoreProductsThanTheOrderMayHold(): void
    {
        $product = ['productId' => 'SOFA-OSLO-3S', 'name' => 'Oslo three-seater sofa, grey', 'price' => '18990.00', 'quantity' => 1];

        $this->postOrder($this->orderPayload(['products' => array_fill(0, CreateOrderRequest::MAX_PRODUCTS + 1, $product)]));

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/products'], $this->errorPointers($this->responseBody()));
    }

    public function testRejectsEmptyProductList(): void
    {
        $this->postOrder($this->orderPayload(['products' => []]));

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/products'], $this->errorPointers($this->responseBody()));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidDeliveryDates(): iterable
    {
        yield 'wrong format' => ['05.10.2026'];
        yield 'with time' => ['2026-10-05T10:00:00Z'];
        yield 'overflowing day' => ['2026-02-30'];
        yield 'overflowing month' => ['2026-13-01'];
    }

    #[DataProvider('provideInvalidDeliveryDates')]
    public function testRejectsDeliveryDateThatIsNotACalendarDate(string $date): void
    {
        $this->postOrder($this->orderPayload(['expectedDeliveryDate' => $date]));

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], $this->errorPointers($this->responseBody()));
    }

    public function testRejectsMissingRequiredField(): void
    {
        $payload = $this->orderPayload();
        unset($payload['orderId']);

        $this->postOrder($payload);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/orderId'], $this->errorPointers($this->responseBody()));
    }

    public function testRejectsMalformedJson(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/partners/PRT-1042/orders',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"orderId": ',
        );

        $this->assertProblem(400, 'malformed-request');
    }

    public function testRejectsNonJsonContentType(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/partners/PRT-1042/orders',
            server: ['CONTENT_TYPE' => 'text/xml'],
            content: '<order/>',
        );

        $this->assertProblem(415, 'unsupported-media-type');
    }
}
