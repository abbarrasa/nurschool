<?php

namespace Nurschool\OAuth\Provider;

final readonly class FacebookProvider implements ProviderInterface
{
    public function __construct(private string $clientId, #[\SensitiveParameter] private string $clientSecret, private string $version)
    {
    }

    public function name(): string
    {
        return 'facebook';
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $this->assertConfigured();
        return 'https://www.facebook.com/'.$this->version.'/dialog/oauth?'.http_build_query([
            'client_id' => $this->clientId, 'redirect_uri' => $redirectUri,
            'response_type' => 'code', 'scope' => 'email', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function tokenRequest(string $redirectUri, #[\SensitiveParameter] string $code): ProviderRequest
    {
        $this->assertConfigured();
        return new ProviderRequest('POST', $this->graphUrl('/oauth/access_token'), ['body' => [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $redirectUri,
        ]]);
    }

    public function profileRequest(#[\SensitiveParameter] string $accessToken): ProviderRequest
    {
        $this->assertConfigured();
        return new ProviderRequest('GET', $this->graphUrl('/me'), [
            'auth_bearer' => $accessToken,
            'query' => ['fields' => 'id,email', 'appsecret_proof' => hash_hmac('sha256', $accessToken, $this->clientSecret)],
        ]);
    }

    public function identity(array $profile): array
    {
        return ['subject' => $profile['id'] ?? null, 'email' => $profile['email'] ?? null];
    }

    private function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->version.$path;
    }

    private function assertConfigured(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new \RuntimeException('Facebook OAuth credentials are not configured.');
        }
    }
}
