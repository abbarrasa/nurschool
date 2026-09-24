<?php

namespace Nurschool\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Nurschool\Registration\RegistrationService;
use Symfony\Component\HttpFoundation\{JsonResponse, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @implements ProcessorInterface<mixed, JsonResponse> */
final readonly class RegistrationProcessor implements ProcessorInterface
{
    public function __construct(private RegistrationService $registration, private RequestStack $requests, private TranslatorInterface $translator)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $request = $this->requests->getCurrentRequest() ?? throw new \LogicException('An HTTP request is required.');
        if ($request->getContentTypeFormat() !== 'json') {
            throw new UnsupportedMediaTypeHttpException('JSON is required.');
        }
        
        $this->registration->register($request->toArray(), $request->getLocale());

        return new JsonResponse(['message' => $this->translator->trans('registration.success')], 201, ['Cache-Control' => 'no-store, private']);
    }
}
