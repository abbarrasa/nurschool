<?php

namespace Nurschool\OAuth\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('nurschool.oauth_provider')]
interface ProviderInterface
{
    public function name(): string;

    public function authorizationUrl(string $redirectUri, string $state): string;

    public function tokenRequest(string $redirectUri, #[\SensitiveParameter] string $code): ProviderRequest;

    public function profileRequest(#[\SensitiveParameter] string $accessToken): ProviderRequest;

    /**
     * Apply provider-specific trust checks and map its profile to the common identity.
     *
     * @param array<string, mixed> $profile
     * @return array{subject: mixed, email: mixed}
     */
    public function identity(array $profile): array;
}
