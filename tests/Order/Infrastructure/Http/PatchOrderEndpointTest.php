<?php

declare(strict_types=1);

namespace App\Tests\Order\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;

final class PatchOrderEndpointTest extends ApiTestCase
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
        $this->postOrder($this->orderPayload());
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
        $this->postOrder($this->orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19']);
        $first = $this->responseBody();
        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($first['expectedDeliveryDate'], $this->responseBody()['expectedDeliveryDate']);
    }

    public function testUnknownOrderIsNotFound(): void
    {
        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19'], orderId: 'WEB-UNKNOWN');

        $this->assertProblem(404, 'order-not-found');
        $problem = $this->responseBody();
        self::assertSame('Order Not Found', $problem['title']);
        self::assertSame($this->orderPath('WEB-UNKNOWN'), $problem['instance']);
    }

    public function testAnotherPartnerCannotTouchTheOrder(): void
    {
        $this->postOrder($this->orderPayload(), 'PRT-1042');

        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19'], partnerId: self::OTHER_PARTNER_ID);

        $this->assertProblem(404, 'order-not-found');

        $this->getOrder(partnerId: 'PRT-1042');
        self::assertSame('2026-10-05', $this->responseBody()['expectedDeliveryDate']);
    }

    public function testNullDeliveryDateIsRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => null]);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], $this->errorPointers($this->responseBody()));
    }

    public function testOverflowingCalendarDateIsRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => '2026-02-30']);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], $this->errorPointers($this->responseBody()));

        $this->getOrder();
        self::assertSame('2026-10-05', $this->responseBody()['expectedDeliveryDate']);
    }

    public function testEmptyPatchIsRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->patchOrder([]);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/expectedDeliveryDate'], $this->errorPointers($this->responseBody()));
    }

    public function testFieldsOutsideTheContractAreRejected(): void
    {
        $this->postOrder($this->orderPayload());

        $this->patchOrder(['expectedDeliveryDate' => '2026-10-19', 'totalValue' => '5.00']);

        $this->assertProblem(422, 'validation-failed');
        self::assertSame(['/totalValue'], $this->errorPointers($this->responseBody()));

        $this->getOrder();
        $body = $this->responseBody();
        self::assertSame('47940.00', $body['totalValue']);
        self::assertSame('2026-10-05', $body['expectedDeliveryDate']);
    }
}
