<?php

namespace Nurschool\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nurschool\Entity\{Role, User, SocialIdentity};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SocialLoginTest extends WebTestCase
{
    protected function setUp(): void
    {
        foreach (['GOOGLE', 'FACEBOOK'] as $provider) {
            $_SERVER['OAUTH_'.$provider.'_CLIENT_ID'] = $_ENV['OAUTH_'.$provider.'_CLIENT_ID'] = 'test-id';
            $_SERVER['OAUTH_'.$provider.'_CLIENT_SECRET'] = $_ENV['OAUTH_'.$provider.'_CLIENT_SECRET'] = 'test-secret';
        }
    }

    /** @dataProvider providers */
    public function testSuccessfulLoginAndReturningIdentity(string $provider): void
    {
        $browser = self::createClient();
        $browser->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $em->persist((new Role())->setName('ROLE_USER')->setTranslationId('app.roles.user'));
        $em->flush();
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            self::getContainer()->get('test.oauth_http_client')->setResponseFactory([
                new MockResponse('{"access_token":"test-token"}'),
                new MockResponse(json_encode(['sub' => '123', 'id' => '123', 'email' => $attempt === 0 ? 'social@example.com' : 'changed@example.com', 'email_verified' => true])),
            ]);
            $browser->jsonRequest('POST', '/api/oauth/'.$provider.'/start');
            self::assertResponseIsSuccessful();
            $url = json_decode($browser->getResponse()->getContent(), true)['url'];
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('http://localhost:8080/api/oauth/'.$provider.'/callback', $query['redirect_uri']);
            $browser->request('GET', '/api/oauth/'.$provider.'/callback', ['state' => $query['state'], 'code' => 'code']);
            self::assertResponseRedirects('/');
            $browser->request('GET', '/api/me', server: ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
            self::assertSame('social@example.com', json_decode($browser->getResponse()->getContent(), true)['email']);
        }
        self::assertSame(1, $em->getRepository(User::class)->count([]));
        self::assertSame(1, $em->getRepository(SocialIdentity::class)->count([]));
        self::assertSame(['ROLE_USER'], $em->getRepository(User::class)->findOneBy([])->getRoles());
    }

    public function testExistingEmailIsNotLinkedAndProviderFailuresAllowRetry(): void
    {
        $browser = self::createClient();
        $browser->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $em->persist((new User())->setEmail('existing@example.com')->setPassword('unused')->markVerified());
        $em->flush();
        foreach ([
            [new MockResponse('{"access_token":"token"}'), new MockResponse('{"sub":"1","email":"existing@example.com","email_verified":true}')],
            [new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400])],
            [new MockResponse('{"access_token":"token"}'), new MockResponse('{"sub":"1"}')],
        ] as $responses) {
            self::getContainer()->get('test.oauth_http_client')->setResponseFactory($responses);
            $browser->jsonRequest('POST', '/api/oauth/google/start');
            parse_str(parse_url(json_decode($browser->getResponse()->getContent(), true)['url'], PHP_URL_QUERY), $query);
            $browser->request('GET', '/api/oauth/google/callback', ['state' => $query['state'], 'code' => 'code']);
            self::assertResponseRedirects('/login?oauth_error=1');
            $browser->request('GET', '/api/me');
            self::assertResponseStatusCodeSame(401);
        }
        self::assertSame(0, $em->getRepository(SocialIdentity::class)->count([]));
        self::assertSame(1, $em->getRepository(User::class)->count([]));
    }

    public static function providers(): iterable
    {
        yield ['google'];
        yield ['facebook'];
    }

    public function testCancellationInvalidStateReplayAndRetry(): void
    {
        $browser = self::createClient();
        $browser->jsonRequest('POST', '/api/oauth/google/start');
        parse_str(parse_url(json_decode($browser->getResponse()->getContent(), true)['url'], PHP_URL_QUERY), $query);
        foreach ([['state' => $query['state'], 'error' => 'access_denied'], ['state' => $query['state'], 'code' => 'replay'], ['state' => ['bad'], 'code' => 'bad']] as $payload) {
            $browser->request('GET', '/api/oauth/google/callback', $payload);
            self::assertResponseRedirects('/login?oauth_error=1');
        }
        $browser->jsonRequest('POST', '/api/oauth/google/start');
        self::assertResponseIsSuccessful();
        $browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testMissingConfigurationReturnsTranslatedRetryMessage(): void
    {
        $_SERVER['OAUTH_GOOGLE_CLIENT_SECRET'] = $_ENV['OAUTH_GOOGLE_CLIENT_SECRET'] = '';
        $browser = self::createClient();
        $browser->jsonRequest('POST', '/api/oauth/google/start', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        self::assertResponseStatusCodeSame(503);
        self::assertSame(['message' => 'Social sign-in failed. Try again or use your usual sign-in method.'], json_decode($browser->getResponse()->getContent(), true));
        self::assertStringContainsString('no-store', $browser->getResponse()->headers->get('Cache-Control'));
    }

    public function testViewsAndPrivateOperations(): void
    {
        $browser = self::createClient();
        foreach (['/login', '/register'] as $url) {
            $browser->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('#social-login-app');
            self::assertSelectorTextContains('#social-login-app', 'Google');
            self::assertSelectorTextContains('#social-login-app', 'Facebook');
        }
        $browser->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('/oauth/', $browser->getResponse()->getContent());
        $browser->request('POST', '/api/oauth/google/start');
        self::assertResponseStatusCodeSame(415);
        $browser->jsonRequest('POST', '/api/oauth/unknown/start');
        self::assertResponseStatusCodeSame(404);
    }
}
