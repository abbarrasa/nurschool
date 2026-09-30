<?php

namespace Nurschool\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Nurschool\PasswordReset\PasswordResetService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{JsonResponse, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @implements ProcessorInterface<mixed, JsonResponse> */
final readonly class PasswordResetProcessor implements ProcessorInterface
{
    public function __construct(
        private PasswordResetService $reset,
        private RequestStack $requests,
        private TranslatorInterface $translator,
        #[Autowire(service: 'limiter.password_reset_submissions')] private RateLimiterFactoryInterface $submissions,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $request = $this->requests->getCurrentRequest() ?? throw new \LogicException('An HTTP request is required.');
        $ip = $request->getClientIp();
        if ($ip === null) {
            throw new TooManyRequestsHttpException(60, 'Unable to determine the client address.');
        }
        // Invalid submissions consume quota before parsing, token lookup or password hashing.
        $limit = $this->submissions->create(hash('sha256', $ip))->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, 'Password reset submission limit exceeded.');
        }
        if ($request->getContentTypeFormat() !== 'json') {
            throw new UnsupportedMediaTypeHttpException('JSON is required.');
        }

        $this->reset->reset($request->toArray());

        return new JsonResponse(['message' => $this->translator->trans('password_reset.success')], 200, ['Cache-Control' => 'no-store, private']);
    }
}
