<?php

namespace Nurschool\Tests\Unit;

use Nurschool\Security\LoginAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

final class LoginAuthenticatorTest extends TestCase
{
    public function testSupportsOnlyPostLoginRoute(): void
    {
        $authenticator = new LoginAuthenticator();
        $request = Request::create('/api/login', 'POST');
        self::assertFalse($authenticator->supports($request));
        $request->attributes->set('_route', 'api_login');
        self::assertTrue($authenticator->supports($request));
        $request->setMethod('GET');
        self::assertFalse($authenticator->supports($request));
    }

    public function testPasswordIsPassedUnchangedToSymfonyHasher(): void
    {
        $request = Request::create('/api/login', 'POST', server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'user@example.com', 'password' => ' password with spaces ']));
        $passport = (new LoginAuthenticator())->authenticate($request);
        self::assertSame('user@example.com', $passport->getBadge(UserBadge::class)->getUserIdentifier());
        self::assertSame(' password with spaces ', $passport->getBadge(PasswordCredentials::class)->getPassword());
    }
}
