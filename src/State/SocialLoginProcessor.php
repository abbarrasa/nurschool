<?php

namespace Nurschool\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Nurschool\OAuth\{Flow, ProviderClient};
use Symfony\Component\HttpFoundation\{JsonResponse, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @implements ProcessorInterface<mixed, JsonResponse> */
final readonly class SocialLoginProcessor implements ProcessorInterface
{
    public function __construct(private RequestStack $requests, private Flow $flow, private ProviderClient $client, private TranslatorInterface $translator)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $request = $this->requests->getCurrentRequest() ?? throw new \LogicException('An HTTP request is required.');
        if ($request->getContentTypeFormat() !== 'json') {
            throw new UnsupportedMediaTypeHttpException('JSON is required.');
        }
        $provider = (string) $request->attributes->get('provider');
        try {
            $state = $this->flow->begin($request->getSession(), $provider);
            return new JsonResponse(['url' => $this->client->authorizationUrl($provider, $state)], headers: ['Cache-Control' => 'no-store, private']);
        } catch (\RuntimeException) {
            return new JsonResponse(['message' => $this->translator->trans('oauth.error')], 503, ['Cache-Control' => 'no-store, private']);
        }
    }
}
