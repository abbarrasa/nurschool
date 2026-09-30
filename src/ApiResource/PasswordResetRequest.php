<?php

namespace Nurschool\ApiResource;

use ApiPlatform\Metadata\{ApiResource, Post};
use Nurschool\State\PasswordResetRequestProcessor;

#[ApiResource(operations: [new Post(
    uriTemplate: '/password-reset-requests', formats: ['json' => ['application/json']],
    read: false, deserialize: false, validate: false, output: false,
    processor: PasswordResetRequestProcessor::class, name: 'api_password_reset_requests',
)])]
final class PasswordResetRequest
{
    public string $email = '';
}
