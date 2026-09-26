<?php

namespace Nurschool\ApiResource;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use Nurschool\State\SocialLoginProcessor;

#[ApiResource(operations: [
    new Post(uriTemplate: '/oauth/{provider}/start', requirements: ['provider' => 'google|facebook'],
        stateless: false, read: false, deserialize: false, validate: false, output: false,
        openapi: false, processor: SocialLoginProcessor::class, name: 'api_oauth_start'),
    new Get(uriTemplate: '/oauth/{provider}/callback', requirements: ['provider' => 'google|facebook'],
        stateless: false, read: false, output: false, openapi: false, name: 'api_oauth_callback'),
], formats: ['json' => ['application/json']])]
final class SocialLogin
{
}
