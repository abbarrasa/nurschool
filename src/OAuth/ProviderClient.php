<?php

namespace Nurschool\OAuth;

use Nurschool\OAuth\Provider\{ProviderInterface, ProviderRequest};
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ProviderClient
{
    /** @var array<string, ProviderInterface> */
    private array $providers;

    /** @param iterable<ProviderInterface> $providers */
    public function __construct(
        private HttpClientInterface $client,
        #[AutowireIterator('nurschool.oauth_provider')] iterable $providers,
        private string $baseUrl,
    ) {
        $registered = [];
        foreach ($providers as $provider) {
            $name = $provider->name();
            if (isset($registered[$name])) {
                throw new \LogicException('OAuth provider names must be unique.');
            }
            $registered[$name] = $provider;
        }
        $this->providers = $registered;
    }

    private function provider(string $name): ProviderInterface
    {
        return $this->providers[$name] ?? throw new \RuntimeException('OAuth provider is not registered.');
    }

    private function callback(string $provider): string
    {
        return rtrim($this->baseUrl, '/').'/api/oauth/'.$provider.'/callback';
    }

    public function authorizationUrl(string $provider, string $state): string
    {
        return $this->provider($provider)->authorizationUrl($this->callback($provider), $state);
    }

    /** @return array{subject: string, email: string} */
    public function identity(string $provider, #[\SensitiveParameter] string $code): array
    {
        $service = $this->provider($provider);
        $token = $this->request($service->tokenRequest($this->callback($provider), $code));
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('OAuth access token is missing.');
        }
        $identity = $service->identity($this->request($service->profileRequest($accessToken)));
        $subject = $identity['subject'];
        $email = $identity['email'];
        if (!is_string($subject) || $subject === '' || strlen($subject) > 255
            || !is_string($email) || strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('OAuth profile does not contain an acceptable identity and email.');
        }
        return ['subject' => $subject, 'email' => strtolower($email)];
    }

    /** @return array<string, mixed> */
    private function request(ProviderRequest $request): array
    {
        // Enforce transport safeguards consistently across all provider implementations.
        return $this->client->request($request->method, $request->url, [
            ...$request->options, 'timeout' => 10, 'max_redirects' => 0,
        ])->toArray();
    }
}
