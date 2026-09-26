<?php

namespace Nurschool\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(operations: [new Post(
    uriTemplate: '/login', status: 200, formats: ['json' => ['application/json']],
    stateless: false, read: false, deserialize: false, validate: false,
    write: false, output: false, name: 'api_login',
)])]
final class Login
{
    public string $email = '';
    public string $password = '';
}
