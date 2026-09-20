<?php

declare(strict_types=1);

namespace App\Tests\Shared\Http\EventListener;

use App\Order\Domain\Exception\DuplicateOrderException;
use App\Shared\Http\EventListener\ProblemDetailsListener;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

use const JSON_THROW_ON_ERROR;

final class ProblemDetailsListenerTest extends TestCase
{
    private const string BASE_URI = 'https://problems.example.test';

    private ProblemDetailsListener $listener;

    protected function setUp(): void
    {
        $this->listener = new ProblemDetailsListener(self::BASE_URI);
    }

    public function testDomainProblemMapsToItsOwnStatusTitleAndType(): void
    {
        $event = $this->handle(new DuplicateOrderException('PRT-1042', 'WEB-104172'), '/api/v1/partners/PRT-1042/orders');

        self::assertSame(409, $event->getResponse()?->getStatusCode());
        self::assertSame([
            'type' => self::BASE_URI . '/duplicate-order',
            'title' => 'Duplicate Order',
            'status' => 409,
            'detail' => 'Order "WEB-104172" already exists for partner "PRT-1042".',
            'instance' => '/api/v1/partners/PRT-1042/orders',
        ], $this->decodeBody($event));
    }

    public function testValidationFailureListsOneJsonPointerPerViolation(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should be greater than or equal to 1.', null, [], null, 'products[0].quantity', 0),
            new ConstraintViolation('This value should not be blank.', null, [], null, 'orderId', ''),
        ]);
        $exception = new HttpException(422, 'ignored', new ValidationFailedException(null, $violations));

        $event = $this->handle($exception, '/api/v1/partners/PRT-1042/orders');

        $body = $this->decodeBody($event);
        self::assertSame(422, $body['status']);
        self::assertSame(self::BASE_URI . '/validation-failed', $body['type']);
        self::assertSame([
            ['pointer' => '/products/0/quantity', 'message' => 'This value should be greater than or equal to 1.'],
            ['pointer' => '/orderId', 'message' => 'This value should not be blank.'],
        ], $body['errors']);
    }

    public function testHttpExceptionKeepsItsStatusAndHeaders(): void
    {
        $event = $this->handle(new MethodNotAllowedHttpException(['GET', 'PATCH']), '/api/v1/partners/PRT-1042/orders/WEB-104172');

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, PATCH', $response->headers->get('Allow'));
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $body = $this->decodeBody($event);
        self::assertSame(self::BASE_URI . '/method-not-allowed', $body['type']);
        self::assertSame('Method Not Allowed', $body['title']);
    }

    public function testUnexpectedErrorIsAnOpaqueInternalServerError(): void
    {
        $event = $this->handle(new RuntimeException('secret database password in message'), '/api/v1/partners/PRT-1042/orders');

        $body = $this->decodeBody($event);
        self::assertSame(500, $body['status']);
        self::assertSame(self::BASE_URI . '/internal-error', $body['type']);
        self::assertStringNotContainsString('secret', json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function handle(Throwable $throwable, string $path): ExceptionEvent
    {
        $event = new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        ($this->listener)($event);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(ExceptionEvent $event): array
    {
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        $content = $response->getContent();
        self::assertIsString($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
