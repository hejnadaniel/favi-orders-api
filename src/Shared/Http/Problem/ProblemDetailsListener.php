<?php

declare(strict_types=1);

namespace App\Shared\Http\Problem;

use App\Shared\Problem\Problem;
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

        if ($exception instanceof Problem) {
            $event->setResponse($this->problem($exception->status(), $exception->slug(), $exception->title(), $exception->getMessage(), $instance));

            return;
        }

        if ($exception instanceof ExtraAttributesException) {
            $event->setResponse($this->unknownFieldsProblem($exception, $instance));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $validationFailure = $exception->getPrevious();
            if ($validationFailure instanceof ValidationFailedException) {
                $event->setResponse($this->validationProblem($validationFailure, $instance));

                return;
            }

            $status = $exception->getStatusCode();
            $response = $this->problem($status, self::slugForStatus($status), self::titleForStatus($status), $exception->getMessage(), $instance);
            foreach ($exception->getHeaders() as $name => $value) {
                if (\is_string($value)) {
                    $response->headers->set($name, $value);
                }
            }
            $event->setResponse($response);

            return;
        }

        $event->setResponse($this->problem(500, 'internal-error', 'Internal Server Error', 'An unexpected error occurred.', $instance));
    }

    private function validationProblem(ValidationFailedException $exception, string $instance): JsonResponse
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[] = [
                'pointer' => self::jsonPointer($violation->getPropertyPath()),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $this->problem(422, 'validation-failed', 'Validation Failed', 'One or more fields are invalid.', $instance, ['errors' => $errors]);
    }

    private function unknownFieldsProblem(ExtraAttributesException $exception, string $instance): JsonResponse
    {
        $errors = [];
        foreach ($exception->getExtraAttributes() as $attribute) {
            $errors[] = [
                'pointer' => '/' . $attribute,
                'message' => 'This field is not part of the request schema.',
            ];
        }

        return $this->problem(422, 'validation-failed', 'Validation Failed', 'One or more fields are invalid.', $instance, ['errors' => $errors]);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function problem(int $status, string $slug, string $title, string $detail, string $instance, array $extensions = []): JsonResponse
    {
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

    private static function slugForStatus(int $status): string
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

    private static function titleForStatus(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'HTTP Error';
    }

    private static function jsonPointer(string $propertyPath): string
    {
        return '/' . str_replace(['[', ']', '.'], ['/', '', '/'], $propertyPath);
    }
}
