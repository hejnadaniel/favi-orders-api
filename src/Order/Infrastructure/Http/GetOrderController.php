<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http;

use App\Order\Application\OrderFinder;
use App\Order\Infrastructure\Http\Response\OrderResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetOrderController
{
    public function __construct(
        private readonly OrderFinder $orderFinder,
    ) {
    }

    #[Route('/partners/{partnerId}/orders/{orderId}', name: 'order_get', methods: ['GET'])]
    public function __invoke(string $partnerId, string $orderId): JsonResponse
    {
        return new JsonResponse(OrderResponse::fromOrder($this->orderFinder->get($partnerId, $orderId)));
    }
}
