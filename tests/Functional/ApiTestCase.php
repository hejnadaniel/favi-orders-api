<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

abstract class ApiTestCase extends WebTestCase
{
    protected const string PARTNER_ID = 'PRT-1042';
    protected const string OTHER_PARTNER_ID = 'PRT-2087';
    protected const string ORDER_ID = 'WEB-104172';
    protected const string PROBLEM_TYPE_BASE_URI = 'https://api.favi.test/problems';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function orderPayload(array $overrides = []): array
    {
        return [
            'orderId' => self::ORDER_ID,
            'expectedDeliveryDate' => '2026-10-05',
            'totalValue' => '47940.00',
            'products' => [
                ['productId' => 'SOFA-OSLO-3S', 'name' => 'Oslo three-seater sofa, grey', 'price' => '18990.00', 'quantity' => 2],
                ['productId' => 'CHAIR-VELVET-GRN', 'name' => 'Velvet dining chair, green', 'price' => '2490.00', 'quantity' => 4],
            ],
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postOrder(array $payload, string $partnerId = self::PARTNER_ID): void
    {
        $this->client->jsonRequest('POST', \sprintf('/api/v1/partners/%s/orders', $partnerId), $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function patchOrder(array $payload, string $orderId = self::ORDER_ID, string $partnerId = self::PARTNER_ID, string $contentType = 'application/merge-patch+json'): void
    {
        $this->client->request(
            'PATCH',
            $this->orderPath($orderId, $partnerId),
            server: ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    protected function getOrder(string $orderId = self::ORDER_ID, string $partnerId = self::PARTNER_ID): void
    {
        $this->client->request('GET', $this->orderPath($orderId, $partnerId), server: ['HTTP_ACCEPT' => 'application/json']);
    }

    protected function orderPath(string $orderId = self::ORDER_ID, string $partnerId = self::PARTNER_ID): string
    {
        return \sprintf('/api/v1/partners/%s/orders/%s', $partnerId, $orderId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseBody(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $problem
     *
     * @return list<string>
     */
    protected function errorPointers(array $problem): array
    {
        self::assertIsArray($problem['errors'] ?? null, 'problem details must carry an errors[] list');

        /** @var list<array{pointer: string, message: string}> $errors */
        $errors = $problem['errors'];

        return array_column($errors, 'pointer');
    }

    protected function assertProblem(int $status, string $slug): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = $this->responseBody();
        self::assertSame(self::PROBLEM_TYPE_BASE_URI . '/' . $slug, $problem['type']);
        self::assertSame($status, $problem['status']);
    }
}
