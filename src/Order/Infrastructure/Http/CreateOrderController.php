<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http;

use App\Order\Application\CreateOrder;
use App\Order\Application\OrderCreator;
use App\Order\Domain\DecimalAmount;
use App\Order\Domain\ProductLine;
use App\Order\Infrastructure\Http\Request\CalendarDate;
use App\Order\Infrastructure\Http\Request\CreateOrderProductRequest;
use App\Order\Infrastructure\Http\Request\CreateOrderRequest;
use App\Order\Infrastructure\Http\Response\OrderResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateOrderController
{
    public function __construct(
        private readonly OrderCreator $orderCreator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/partners/{partnerId}/orders', name: 'order_create', methods: ['POST'])]
    public function __invoke(
        string $partnerId,
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateOrderRequest $request,
    ): JsonResponse {
        $order = $this->orderCreator->create(self::command($partnerId, $request));

        $response = new JsonResponse(OrderResponse::fromOrder($order), Response::HTTP_CREATED);
        $response->headers->set('Location', $this->urlGenerator->generate('api_v1_order_get', [
            'partnerId' => $order->partnerId,
            'orderId' => $order->orderId,
        ]));

        return $response;
    }

    private static function command(string $partnerId, CreateOrderRequest $request): CreateOrder
    {
        return new CreateOrder(
            partnerId: $partnerId,
            orderId: $request->orderId,
            expectedDeliveryDate: CalendarDate::fromValidated($request->expectedDeliveryDate),
            totalValue: DecimalAmount::fromString($request->totalValue),
            products: array_map(
                static fn (CreateOrderProductRequest $product): ProductLine => new ProductLine(
                    $product->productId,
                    $product->name,
                    DecimalAmount::fromString($product->price),
                    $product->quantity,
                ),
                $request->products,
            ),
        );
    }
}
