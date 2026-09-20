<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Handler\CreateOrderHandler;
use App\Order\Infrastructure\Http\Factory\CreateOrderCommandFactory;
use App\Order\Infrastructure\Http\Factory\OrderResponseFactory;
use App\Order\Infrastructure\Http\Request\CreateOrderRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateOrderController
{
    public function __construct(
        private readonly CreateOrderHandler $createOrderHandler,
        private readonly CreateOrderCommandFactory $createOrderCommandFactory,
        private readonly OrderResponseFactory $orderResponseFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/partners/{partnerId}/orders', name: 'order_create', methods: ['POST'])]
    public function __invoke(
        string $partnerId,
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateOrderRequest $request,
    ): JsonResponse {
        $command = $this->createOrderCommandFactory->create($partnerId, $request);
        $order = $this->createOrderHandler->handle($command);

        $response = new JsonResponse($this->orderResponseFactory->create($order), Response::HTTP_CREATED);
        $response->headers->set('Location', $this->urlGenerator->generate('api_v1_order_get', [
            'partnerId' => $order->partnerId,
            'orderId' => $order->orderId,
        ]));

        return $response;
    }
}
