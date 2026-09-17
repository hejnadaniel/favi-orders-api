<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http;

use App\Order\Application\ChangeDeliveryDate;
use App\Order\Application\OrderDeliveryDateUpdater;
use App\Order\Infrastructure\Http\Request\CalendarDate;
use App\Order\Infrastructure\Http\Request\PatchOrderRequest;
use App\Order\Infrastructure\Http\Response\OrderResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

final class PatchOrderController
{
    public function __construct(
        private readonly OrderDeliveryDateUpdater $orderDeliveryDateUpdater,
    ) {
    }

    #[Route(
        path: '/api/v1/partners/{partnerId}/orders/{orderId}',
        name: 'api_v1_order_patch',
        requirements: ['partnerId' => '[^/]{1,64}', 'orderId' => '[^/]{1,64}'],
        methods: ['PATCH'],
    )]
    public function __invoke(
        string $partnerId,
        string $orderId,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        )]
        PatchOrderRequest $request,
    ): JsonResponse {
        $order = $this->orderDeliveryDateUpdater->update(
            new ChangeDeliveryDate($partnerId, $orderId, CalendarDate::fromValidated($request->expectedDeliveryDate)),
        );

        return new JsonResponse(OrderResponse::fromOrder($order));
    }
}
