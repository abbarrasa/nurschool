<?php

namespace Nurschool\ApiResource;

use ApiPlatform\Metadata\{ApiResource, Post};
use Nurschool\State\PasswordResetProcessor;

#[ApiResource(operations: [new Post(
    uriTemplate: '/password-resets', formats: ['json' => ['application/json']],
    read: false, deserialize: false, validate: false, output: false,
    processor: PasswordResetProcessor::class, name: 'api_password_resets',
)])]
final class PasswordReset
{
    public string $token = '';
    public string $password = '';
}
