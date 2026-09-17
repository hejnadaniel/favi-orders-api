<?php

declare(strict_types=1);

namespace App\Tests\Order\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

/**
 * Shared HTTP helpers for the functional suite. Every test runs inside a
 * transaction that dama/doctrine-test-bundle rolls back afterwards.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const string PARTNER_ID = 'PARTNER_A';
    protected const string ORDER_ID = 'ORD-2026-00001';

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
    protected static function orderPayload(array $overrides = []): array
    {
        return [
            'orderId' => self::ORDER_ID,
            'expectedDeliveryDate' => '2026-06-15',
            'totalValue' => '1299.99',
            'products' => [
                ['productId' => 'SKU-001', 'name' => 'Bluetooth Headphones', 'price' => '129.99', 'quantity' => 2],
                ['productId' => 'SKU-002', 'name' => 'USB-C Cable, 2 m', 'price' => '9.99', 'quantity' => 4],
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
            self::orderPath($orderId, $partnerId),
            server: ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    protected function getOrder(string $orderId = self::ORDER_ID, string $partnerId = self::PARTNER_ID): void
    {
        $this->client->request('GET', self::orderPath($orderId, $partnerId), server: ['HTTP_ACCEPT' => 'application/json']);
    }

    protected static function orderPath(string $orderId = self::ORDER_ID, string $partnerId = self::PARTNER_ID): string
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
    protected static function errorPointers(array $problem): array
    {
        self::assertIsArray($problem['errors'] ?? null, 'problem details must carry an errors[] list');

        /** @var list<array{pointer: string, message: string}> $errors */
        $errors = $problem['errors'];

        return array_column($errors, 'pointer');
    }

    protected static function assertProblem(int $status, string $slug): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }
}
