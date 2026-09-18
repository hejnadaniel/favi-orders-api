<?php

declare(strict_types=1);

namespace App\Tests\Order\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;

final class PatchOrderControllerTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideAcceptedContentTypes(): iterable
    {
        yield 'merge patch' => ['application/merge-patch+json'];
        yield 'plain json' => ['application/json'];
    }

    #[DataProvider('provideAcceptedContentTypes')]
    public function testReplacesDeliveryDateAndLeavesEverythingElseUntouched(string $contentType): void
    {
        $this->postOrder(self::orderPayload());
        $created = $this->responseBody();

        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19'], contentType: $contentType);

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
        $this->postOrder(self::orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19']);
        $first = $this->responseBody();
        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($first['expectedDeliveryDate'], $this->responseBody()['expectedDeliveryDate']);
    }

    public function testUnknownOrderIsNotFound(): void
    {
        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19'], orderId: 'WEB-UNKNOWN');

        self::assertProblem(404, 'order-not-found');
        $problem = $this->responseBody();
        self::assertSame('https://api.favi.test/problems/order-not-found', $problem['type']);
        self::assertSame('Order Not Found', $problem['title']);
        self::assertSame(self::orderPath('WEB-UNKNOWN'), $problem['instance']);
    }

    public function testAnotherPartnerCannotTouchTheOrder(): void
    {
        $this->postOrder(self::orderPayload(), 'nabytek-brno');

        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19'], partnerId: 'nabytek-ostrava');

        self::assertProblem(404, 'order-not-found');

        $this->getOrder(partnerId: 'nabytek-brno');
        self::assertSame('2026-10-05', $this->responseBody()['expectedDeliveryDate']);
    }

    public function testNullDeliveryDateIsRejected(): void
    {
        $this->postOrder(self::orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => null]);

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], self::errorPointers($this->responseBody()));
    }

    public function testOverflowingCalendarDateIsRejected(): void
    {
        $this->postOrder(self::orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => '2026-02-30']);

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], self::errorPointers($this->responseBody()));

        $this->getOrder();
        self::assertSame('2026-10-05', $this->responseBody()['expectedDeliveryDate']);
    }

    public function testEmptyPatchIsRejected(): void
    {
        $this->postOrder(self::orderPayload());

        $this->patchOrder([]);

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], self::errorPointers($this->responseBody()));
    }

    public function testFieldsOutsideTheContractAreRejected(): void
    {
        $this->postOrder(self::orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19', 'totalValue' => '0.01']);

        self::assertProblem(422, 'validation-failed');
        self::assertSame(['/totalValue'], self::errorPointers($this->responseBody()));

        $this->getOrder();
        $body = $this->responseBody();
        self::assertSame('47940.00', $body['totalValue']);
        self::assertSame('2026-10-05', $body['expectedDeliveryDate']);
    }
}
