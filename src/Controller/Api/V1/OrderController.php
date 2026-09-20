<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Request\CreateOrderRequest;
use App\Dto\Request\PatchOrderRequest;
use App\Factory\ChangeOrderDeliveryDateFactory;
use App\Factory\CreateOrderFactory;
use App\Factory\OrderResponseFactory;
use App\Service\ChangeOrderDeliveryDateHandler;
use App\Service\CreateOrderHandler;
use App\Service\GetOrderHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/partners/{partnerId}/orders')]
final class OrderController
{
    public function __construct(
        private readonly CreateOrderHandler $createOrderHandler,
        private readonly ChangeOrderDeliveryDateHandler $changeOrderDeliveryDateHandler,
        private readonly GetOrderHandler $getOrderHandler,
        private readonly CreateOrderFactory $createOrderFactory,
        private readonly ChangeOrderDeliveryDateFactory $changeOrderDeliveryDateFactory,
        private readonly OrderResponseFactory $orderResponseFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('', name: 'order_create', methods: ['POST'])]
    public function create(
        string $partnerId,
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateOrderRequest $request,
    ): JsonResponse {
        $dto = $this->createOrderFactory->create($partnerId, $request);
        $order = $this->createOrderHandler->handle($dto);

        $response = new JsonResponse($this->orderResponseFactory->create($order), Response::HTTP_CREATED);
        $response->headers->set('Location', $this->urlGenerator->generate('api_v1_order_get', [
            'partnerId' => $order->partnerId,
            'orderId' => $order->orderId,
        ]));

        return $response;
    }

    #[Route('/{orderId}', name: 'order_get', methods: ['GET'])]
    public function get(string $partnerId, string $orderId): JsonResponse
    {
        $order = $this->getOrderHandler->handle($partnerId, $orderId);

        return new JsonResponse($this->orderResponseFactory->create($order));
    }

    #[Route('/{orderId}', name: 'order_patch', methods: ['PATCH'])]
    public function patch(
        string $partnerId,
        string $orderId,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        )]
        PatchOrderRequest $request,
    ): JsonResponse {
        $dto = $this->changeOrderDeliveryDateFactory->create($partnerId, $orderId, $request);
        $order = $this->changeOrderDeliveryDateHandler->handle($dto);

        return new JsonResponse($this->orderResponseFactory->create($order));
    }
}
