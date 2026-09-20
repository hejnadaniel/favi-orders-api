<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Handler\ChangeOrderDeliveryDateHandler;
use App\Order\Application\Handler\CreateOrderHandler;
use App\Order\Application\Handler\GetOrderHandler;
use App\Order\Infrastructure\Http\Factory\ChangeOrderDeliveryDateDtoFactory;
use App\Order\Infrastructure\Http\Factory\CreateOrderDtoFactory;
use App\Order\Infrastructure\Http\Factory\OrderResponseDtoFactory;
use App\Order\Infrastructure\Http\Request\CreateOrderRequestDto;
use App\Order\Infrastructure\Http\Request\PatchOrderRequestDto;
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
        private readonly CreateOrderDtoFactory $createOrderDtoFactory,
        private readonly ChangeOrderDeliveryDateDtoFactory $changeOrderDeliveryDateDtoFactory,
        private readonly OrderResponseDtoFactory $orderResponseDtoFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('', name: 'order_create', methods: ['POST'])]
    public function create(
        string $partnerId,
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateOrderRequestDto $request,
    ): JsonResponse {
        $dto = $this->createOrderDtoFactory->create($partnerId, $request);
        $order = $this->createOrderHandler->handle($dto);

        $response = new JsonResponse($this->orderResponseDtoFactory->create($order), Response::HTTP_CREATED);
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

        return new JsonResponse($this->orderResponseDtoFactory->create($order));
    }

    #[Route('/{orderId}', name: 'order_patch', methods: ['PATCH'])]
    public function patch(
        string $partnerId,
        string $orderId,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        )]
        PatchOrderRequestDto $request,
    ): JsonResponse {
        $dto = $this->changeOrderDeliveryDateDtoFactory->create($partnerId, $orderId, $request);
        $order = $this->changeOrderDeliveryDateHandler->handle($dto);

        return new JsonResponse($this->orderResponseDtoFactory->create($order));
    }
}
