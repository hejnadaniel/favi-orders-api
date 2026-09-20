<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ProblemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ProblemDetailsListener
{
    public function __construct(
        #[Autowire(env: 'PROBLEM_TYPE_BASE_URI')]
        private readonly string $problemTypeBaseUri,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $instance = $event->getRequest()->getPathInfo();

        if ($exception instanceof ProblemInterface) {
            $event->setResponse($this->buildProblemResponse(
                $exception->getStatus(),
                $exception->getSlug(),
                $exception->getTitle(),
                $exception->getMessage(),
                $instance,
            ));

            return;
        }

        if ($exception instanceof ExtraAttributesException) {
            $event->setResponse($this->buildUnknownFieldsResponse($exception, $instance));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $validationFailure = $exception->getPrevious();
            if ($validationFailure instanceof ValidationFailedException) {
                $event->setResponse($this->buildValidationResponse($validationFailure, $instance));

                return;
            }

            $status = $exception->getStatusCode();
            $response = $this->buildProblemResponse(
                $status,
                $this->slugForStatus($status),
                $this->titleForStatus($status),
                $exception->getMessage(),
                $instance,
            );
            foreach ($exception->getHeaders() as $name => $value) {
                if (\is_string($value)) {
                    $response->headers->set($name, $value);
                }
            }
            $event->setResponse($response);

            return;
        }

        $event->setResponse($this->buildProblemResponse(
            500,
            'internal-error',
            'Internal Server Error',
            'An unexpected error occurred.',
            $instance,
        ));
    }

    private function buildValidationResponse(ValidationFailedException $exception, string $instance): JsonResponse
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[] = [
                'pointer' => $this->toJsonPointer($violation->getPropertyPath()),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $this->buildProblemResponse(422, 'validation-failed', 'Validation Failed', 'The request body did not pass validation.', $instance, ['errors' => $errors]);
    }

    private function buildUnknownFieldsResponse(ExtraAttributesException $exception, string $instance): JsonResponse
    {
        $errors = [];
        foreach ($exception->getExtraAttributes() as $attribute) {
            $errors[] = [
                'pointer' => '/' . $attribute,
                'message' => 'This field is not part of the request schema.',
            ];
        }

        return $this->buildProblemResponse(422, 'validation-failed', 'Validation Failed', 'The request body did not pass validation.', $instance, ['errors' => $errors]);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function buildProblemResponse(
        int $status,
        string $slug,
        string $title,
        string $detail,
        string $instance,
        array $extensions = [],
    ): JsonResponse {
        $response = new JsonResponse([
            'type' => $this->problemTypeBaseUri . '/' . $slug,
            'title' => $title,
            'status' => $status,
            'detail' => $detail !== '' ? $detail : $title,
            'instance' => $instance,
            ...$extensions,
        ], $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }

    private function slugForStatus(int $status): string
    {
        return match ($status) {
            400 => 'malformed-request',
            404 => 'not-found',
            405 => 'method-not-allowed',
            406 => 'not-acceptable',
            415 => 'unsupported-media-type',
            422 => 'validation-failed',
            default => 'http-' . $status,
        };
    }

    private function titleForStatus(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'HTTP Error';
    }

    private function toJsonPointer(string $propertyPath): string
    {
        return '/' . str_replace(['[', ']', '.'], ['/', '', '/'], $propertyPath);
    }
}
