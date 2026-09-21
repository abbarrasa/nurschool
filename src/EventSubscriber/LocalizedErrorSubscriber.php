<?php

namespace Nurschool\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/** Keep internal exception details out of browser responses, including in debug mode. */
final readonly class LocalizedErrorSubscriber implements EventSubscriberInterface
{
    public function __construct(private TranslatorInterface $translator, private Environment $twig)
    {
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $headers['Cache-Control'] = 'no-store, private';
        $key = in_array($status, [400, 401, 403, 404, 405, 415, 422, 429], true) ? 'error.'.$status : 'error.generic';
        $request = $event->getRequest();
        if ($status === 400 && $request->attributes->get('_route') === 'api_login') {
            $key = 'login.error.validation';
        }
        $message = $this->translator->trans($key, locale: $request->getLocale());
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            $event->setResponse(new JsonResponse(['message' => $message], $status, $headers));

            return;
        }
        $event->setResponse(new Response($this->twig->render('error.html.twig', [
            'status' => $status, 'message' => $message,
        ]), $status, $headers));
    }

    public static function getSubscribedEvents(): array
    {
        // Let Security handle authentication and Symfony log the original exception first.
        return [KernelEvents::EXCEPTION => ['onException', -64]];
    }
}
