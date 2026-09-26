<?php

namespace Nurschool\Tests\Unit;

use Nurschool\OAuth\ProviderClient;
use Nurschool\OAuth\Provider\{GoogleProvider, FacebookProvider, ProviderInterface, ProviderRequest};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProviderClientTest extends TestCase
{
    /** @dataProvider invalidProfiles */
    public function testRejectsIncompleteOrUnverifiedProfiles(array $profile): void
    {
        $client = new ProviderClient(new MockHttpClient([
            new MockResponse('{"access_token":"token"}'), new MockResponse(json_encode($profile)),
        ]), [new GoogleProvider('id', 'secret')], 'https://example.com');
        $this->expectException(\RuntimeException::class);
        $client->identity('google', 'code');
    }

    public static function invalidProfiles(): iterable
    {
        yield [[]];
        yield [['sub' => '1', 'email' => 'valid@example.com', 'email_verified' => false]];
        yield [['sub' => '1', 'email' => 'invalid', 'email_verified' => true]];
        yield [['sub' => [], 'email' => 'valid@example.com', 'email_verified' => true]];
    }

    public function testRejectsMissingToken(): void
    {
        $client = new ProviderClient(new MockHttpClient(new MockResponse('{}')), [new FacebookProvider('id', 'secret', 'v23.0')], 'https://example.com');
        $this->expectException(\RuntimeException::class);
        $client->identity('facebook', 'code');
    }

    public function testRejectsUnconfiguredProvider(): void
    {
        $client = new ProviderClient(new MockHttpClient(), [], 'https://example.com');
        $this->expectException(\RuntimeException::class);
        $client->authorizationUrl('google', 'state');
    }
    public function testAdditionalProviderWorksWithoutClientChanges(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('name')->willReturn('example');
        $callback = 'https://nurschool.example/api/oauth/example/callback';
        $provider->expects(self::once())->method('authorizationUrl')->with($callback, 'state')
            ->willReturn('https://social.example/authorize?state=state');
        $provider->expects(self::once())->method('tokenRequest')->with($callback, 'code')
            ->willReturn(new ProviderRequest('POST', 'https://social.example/token', ['json' => ['code' => 'code']]));
        $provider->expects(self::once())->method('profileRequest')->with('token')
            ->willReturn(new ProviderRequest('POST', 'https://social.example/profile', ['auth_bearer' => 'token']));
        $provider->expects(self::once())->method('identity')->with(['account' => '42', 'mail' => 'Person@example.com'])
            ->willReturn(['subject' => '42', 'email' => 'Person@example.com']);
        $requests = [];
        $http = new MockHttpClient(static function ($method, $url, $options) use (&$requests) {
            $requests[] = [$method, $url];
            self::assertSame(10.0, $options['timeout']);
            self::assertSame(0, $options['max_redirects']);
            return new MockResponse(count($requests) === 1 ? '{"access_token":"token"}' : '{"account":"42","mail":"Person@example.com"}');
        });
        $client = new ProviderClient($http, [$provider], 'https://nurschool.example/');
        self::assertSame('https://social.example/authorize?state=state', $client->authorizationUrl('example', 'state'));
        self::assertSame(['subject' => '42', 'email' => 'person@example.com'], $client->identity('example', 'code'));
        self::assertSame([['POST', 'https://social.example/token'], ['POST', 'https://social.example/profile']], $requests);
    }

    public function testDuplicateProviderNamesAreRejected(): void
    {
        $this->expectException(\LogicException::class);
        new ProviderClient(new MockHttpClient(), [new GoogleProvider('a', 'b'), new GoogleProvider('c', 'd')], 'https://example.com');
    }

    public function testUnknownProviderCannotExchangeCode(): void
    {
        $client = new ProviderClient(new MockHttpClient(), [], 'https://example.com');
        $this->expectException(\RuntimeException::class);
        $client->identity('unknown', 'code');
    }

}
