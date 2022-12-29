<?php

declare(strict_types=1);

namespace Nurschool\Listener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class JsonExceptionResponseListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        if ($exception instanceof HttpExceptionInterface) {
            $data = [
                'class' => \get_class($exception),
                'code' => $exception->getStatusCode(),
                'message' => $exception->getMessage()
            ];

            $response = $this->prepareResponse($data, $data['code']);
            $event->setResponse($response);
        }
    }

    private function prepareResponse(array $data, int $statusCode): JsonResponse
    {
        $response = new JsonResponse($data, $statusCode);
        $response->headers->set('Server-Time', \strval(\time()));
        $response->headers->set('X-Error-Code', \strval($statusCode));

        return $response;
    }
}