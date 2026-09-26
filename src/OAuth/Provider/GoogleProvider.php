<?php

namespace Nurschool\OAuth\Provider;

final readonly class GoogleProvider implements ProviderInterface
{
    public function __construct(private string $clientId, #[\SensitiveParameter] private string $clientSecret)
    {
    }

    public function name(): string
    {
        return 'google';
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $this->assertConfigured();
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId, 'redirect_uri' => $redirectUri,
            'response_type' => 'code', 'scope' => 'openid email', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function tokenRequest(string $redirectUri, #[\SensitiveParameter] string $code): ProviderRequest
    {
        $this->assertConfigured();
        return new ProviderRequest('POST', 'https://oauth2.googleapis.com/token', ['body' => [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $redirectUri,
        ]]);
    }

    public function profileRequest(#[\SensitiveParameter] string $accessToken): ProviderRequest
    {
        return new ProviderRequest('GET', 'https://openidconnect.googleapis.com/v1/userinfo', ['auth_bearer' => $accessToken]);
    }

    public function identity(array $profile): array
    {
        if (($profile['email_verified'] ?? false) !== true) {
            throw new \RuntimeException('Google email is not verified.');
        }
        return ['subject' => $profile['sub'] ?? null, 'email' => $profile['email'] ?? null];
    }

    private function assertConfigured(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new \RuntimeException('Google OAuth credentials are not configured.');
        }
    }
}
