<?php

namespace Nurschool\ApiResource;

use ApiPlatform\Metadata\{ApiResource, Post};
use Nurschool\State\VerificationProcessor;

#[ApiResource(operations: [new Post(
    uriTemplate: '/account-verifications', formats: ['json' => ['application/json']],
    read: false, deserialize: false, validate: false, output: false,
    processor: VerificationProcessor::class, name: 'api_verification',
)])]
final class AccountVerification
{
    public string $token = '';
}
