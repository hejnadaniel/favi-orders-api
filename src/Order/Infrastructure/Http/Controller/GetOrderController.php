<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Handler\GetOrderHandler;
use App\Order\Infrastructure\Http\Factory\OrderResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetOrderController
{
    public function __construct(
        private readonly GetOrderHandler $getOrderHandler,
        private readonly OrderResponseFactory $orderResponseFactory,
    ) {
    }

    #[Route('/partners/{partnerId}/orders/{orderId}', name: 'order_get', methods: ['GET'])]
    public function __invoke(string $partnerId, string $orderId): JsonResponse
    {
        $order = $this->getOrderHandler->handle($partnerId, $orderId);

        return new JsonResponse($this->orderResponseFactory->create($order));
    }
}
