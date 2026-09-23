<?php

namespace Nurschool\ApiResource;

use ApiPlatform\Metadata\{ApiResource, Post};
use Nurschool\State\RegistrationProcessor;

#[ApiResource(operations: [new Post(
    uriTemplate: '/registrations', formats: ['json' => ['application/json']],
    read: false, deserialize: false, validate: false, output: false,
    processor: RegistrationProcessor::class, name: 'api_registration',
)])]
final class Registration
{
    public string $email = '';
    public string $password = '';
    /** @var list<string> */
    public array $roles = [];
}
