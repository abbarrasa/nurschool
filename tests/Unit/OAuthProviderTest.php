<?php

namespace Nurschool\Tests\Unit;

use Nurschool\OAuth\Provider\{FacebookProvider, GoogleProvider, ProviderInterface};
use PHPUnit\Framework\TestCase;

final class OAuthProviderTest extends TestCase
{
    /** @dataProvider providers */
    public function testProviderBuildsConfiguredRequests(ProviderInterface $provider, string $authorize, string $token, string $profile, string $scope): void
    {
        $callback = 'https://nurschool.example/api/oauth/'.$provider->name().'/callback';
        $url = $provider->authorizationUrl($callback, 'state + &');
        self::assertSame($authorize, strtok($url, '?'));
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
        self::assertSame(['client_id' => 'client', 'redirect_uri' => $callback, 'response_type' => 'code', 'scope' => $scope, 'state' => 'state + &'], $parameters);
        self::assertStringNotContainsString('secret', $url);
        $request = $provider->tokenRequest($callback, 'code');
        self::assertSame('POST', $request->method);
        self::assertSame($token, $request->url);
        self::assertSame(['body' => ['client_id' => 'client', 'client_secret' => 'secret', 'code' => 'code', 'grant_type' => 'authorization_code', 'redirect_uri' => $callback]], $request->options);
        $request = $provider->profileRequest('token');
        self::assertSame('GET', $request->method);
        self::assertSame($profile, $request->url);
        self::assertSame('token', $request->options['auth_bearer']);
        if ($provider instanceof FacebookProvider) {
            self::assertSame(['fields' => 'id,email', 'appsecret_proof' => hash_hmac('sha256', 'token', 'secret')], $request->options['query']);
        }
        self::assertSame(['subject' => '42', 'email' => 'person@example.com'], $provider->identity([
            'sub' => '42', 'id' => '42', 'email' => 'person@example.com', 'email_verified' => true,
        ]));
    }

    public static function providers(): iterable
    {
        yield [new GoogleProvider('client', 'secret'), 'https://accounts.google.com/o/oauth2/v2/auth', 'https://oauth2.googleapis.com/token', 'https://openidconnect.googleapis.com/v1/userinfo', 'openid email'];
        yield [new FacebookProvider('client', 'secret', 'v99.0'), 'https://www.facebook.com/v99.0/dialog/oauth', 'https://graph.facebook.com/v99.0/oauth/access_token', 'https://graph.facebook.com/v99.0/me', 'email'];
    }

    /** @dataProvider missingCredentials */
    public function testMissingCredentialsAreRejected(ProviderInterface $provider, string $operation): void
    {
        $this->expectException(\RuntimeException::class);
        $provider->$operation('https://example.com/callback', 'value');
    }

    public static function missingCredentials(): iterable
    {
        foreach ([['', 'secret'], ['client', '']] as [$client, $secret]) {
            foreach (['authorizationUrl', 'tokenRequest'] as $operation) {
                yield [new GoogleProvider($client, $secret), $operation];
                yield [new FacebookProvider($client, $secret, 'v23.0'), $operation];
            }
            yield [new FacebookProvider($client, $secret, 'v23.0'), 'profileRequest'];
        }
    }
}
