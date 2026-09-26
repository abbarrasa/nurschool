<?php

namespace Nurschool\OAuth\Provider;

/** Describes a provider request without coupling provider configuration to HTTP execution. */
final readonly class ProviderRequest
{
    /** @param array<string, mixed> $options Symfony HttpClient request options. */
    public function __construct(public string $method, public string $url, public array $options = [])
    {
    }
}
