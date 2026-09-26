<?php

namespace Nurschool\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Nurschool\State\CurrentUserProvider;

#[ApiResource(operations: [new Get(
    uriTemplate: '/me', formats: ['json' => ['application/json']], stateless: false,
    security: "is_granted('IS_AUTHENTICATED_FULLY')", provider: CurrentUserProvider::class,
    cacheHeaders: ['max_age' => 0, 'shared_max_age' => 0, 'public' => false],
)])]
final readonly class CurrentUser
{
    public function __construct(public string $email)
    {
    }
}
