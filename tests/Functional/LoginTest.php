<?php

namespace Nurschool\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nurschool\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class LoginTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => '']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $user = (new User())->markVerified()->setEmail('student@example.com');
        $user->setPassword(password_hash('correct-password', PASSWORD_BCRYPT, ['cost' => 4]));
        $em->persist($user);
        $em->flush();
        $em->clear();
    }

    public function testLoginView(): void
    {
        $this->browser->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="email"][required]');
        self::assertSelectorExists('input[type="password"][autocomplete="current-password"]');
        self::assertSelectorExists('[role="alert"]');
        self::assertSelectorExists('script[src="/assets/login.js"]');
    }

    public function testLoginPersistsSessionAndDoesNotExposePassword(): void
    {
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'student@example.com', 'password' => 'correct-password']);
        self::assertResponseIsSuccessful();
        self::assertSame(['email' => 'student@example.com'], json_decode($this->browser->getResponse()->getContent(), true));
        self::assertStringContainsString('no-store', $this->browser->getResponse()->headers->get('Cache-Control'));
        self::assertNotEmpty($this->browser->getCookieJar()->all());
        self::assertTrue($this->browser->getCookieJar()->all()[0]->isHttpOnly());
        $this->browser->request('GET', '/api/me', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(['email' => 'student@example.com'], json_decode($this->browser->getResponse()->getContent(), true));
    }

    public function testAnonymousUserCannotReadProfile(): void
    {
        $this->browser->request('GET', '/api/me', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidCredentialsDoNotRevealWhetherUserExists(): void
    {
        $responses = [];
        foreach (['student@example.com', 'missing@example.com'] as $email) {
            $this->browser->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => 'wrong']);
            self::assertResponseStatusCodeSame(401);
            $responses[] = $this->browser->getResponse()->getContent();
        }
        self::assertSame($responses[0], $responses[1]);
        $this->browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidPayloads(): void
    {
        foreach ([[], ['email' => 'bad', 'password' => 'test'], ['email' => ['x'], 'password' => 'test'],
            ['email' => 'student@example.com'], ['email' => 'student@example.com', 'password' => ''],
            ['email' => 'student@example.com', 'password' => 123],
            ['email' => 'student@example.com', 'password' => str_repeat('x', 4097)]] as $payload) {
            $this->browser->jsonRequest('POST', '/api/login', $payload);
            self::assertResponseStatusCodeSame(400);
        }
        $this->browser->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: '{broken');
        self::assertResponseStatusCodeSame(400);
    }

    public function testHtmlFormCannotLogin(): void
    {
        $this->browser->request('POST', '/api/login', ['email' => 'student@example.com', 'password' => 'correct-password']);
        self::assertResponseStatusCodeSame(415);
    }

    public function testLoginRejectsGetRequests(): void
    {
        $this->browser->request('GET', '/api/login');
        self::assertResponseStatusCodeSame(405);
    }
    private function login(): void
    {
        $this->browser->jsonRequest('POST', '/api/login', ['email' => 'student@example.com', 'password' => 'correct-password']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testHomeRedirectsAnonymousVisitorToLogin(): void
    {
        $this->browser->request('GET', '/');
        self::assertResponseRedirects('/login');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Iniciar sesión');
        self::assertSelectorNotExists('[data-logout-form]');
    }

    public function testHomeIsProtectedAndRendersSharedThemeWithoutEmbeddingUserData(): void
    {
        $this->login();
        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bienvenido a Nurschool');
        self::assertSelectorExists('link[href="/assets/themes/bulma.css"]');
        self::assertSelectorExists('link[href="/assets/app.css"]');
        self::assertSelectorExists('form[action="/logout"][method="post"] input[name="_csrf_token"]');
        self::assertSelectorExists('#home-app[data-profile-url="/api/me"]');
        self::assertStringNotContainsString('student@example.com', $this->browser->getResponse()->getContent());
        self::assertStringContainsString('no-store', $this->browser->getResponse()->headers->get('Cache-Control'));
    }

    public function testLogoutInvalidatesSessionAndRedirectsToLogin(): void
    {
        $this->login();
        $oldCookies = array_map(static fn ($cookie) => clone $cookie, $this->browser->getCookieJar()->all());
        $crawler = $this->browser->request('GET', '/');
        $this->browser->submit($crawler->selectButton('Salir')->form());
        self::assertResponseRedirects('/login');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Iniciar sesión');
        self::assertSelectorNotExists('[data-logout-form]');
        $this->browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        $this->browser->request('GET', '/');
        self::assertResponseRedirects('/login');
        // Even a client that retains the previous session cookie is logged out.
        foreach ($oldCookies as $cookie) {
            $this->browser->getCookieJar()->set($cookie);
        }
        $this->browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidOrMissingLogoutCsrfKeepsSessionActive(): void
    {
        $this->login();
        foreach ([[], ['_csrf_token' => 'invalid']] as $payload) {
            $this->browser->request('POST', '/logout', $payload);
            self::assertResponseStatusCodeSame(403);
            $this->browser->request('GET', '/api/me');
            self::assertResponseStatusCodeSame(200);
        }
    }

    public function testLogoutRejectsGetEvenWithValidToken(): void
    {
        $this->login();
        $crawler = $this->browser->request('GET', '/');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->browser->request('GET', '/logout', ['_csrf_token' => $token]);
        self::assertResponseStatusCodeSame(405);
        $this->browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(200);
    }

    public function testLogoutTokenCannotBeReusedAfterNewLogin(): void
    {
        $this->login();
        $crawler = $this->browser->request('GET', '/');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->browser->request('POST', '/logout', ['_csrf_token' => $token]);
        self::assertResponseRedirects('/login');
        $this->login();
        $this->browser->request('POST', '/logout', ['_csrf_token' => $token]);
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(200);
    }

    public function testBusinessTemplatesCanRenderWithAnAlternativeTheme(): void
    {
        $twig = self::getContainer()->get(\Twig\Environment::class);
        $twig->setLoader(new \Twig\Loader\ChainLoader([
            new \Twig\Loader\ArrayLoader(['ui/alternative.html.twig' =>
                '{% macro stylesheets() %}<link rel="stylesheet" href="/alternative.css">{% endmacro %}'
                .'{% macro classes(name) %}alternative-{{ name }}{% endmacro %}']),
            $twig->getLoader(),
        ]));
        foreach (['security/login.html.twig', 'home/index.html.twig'] as $template) {
            $html = $twig->render($template, ['ui_theme' => 'ui/alternative.html.twig']);
            self::assertStringContainsString('/alternative.css', $html);
            self::assertStringContainsString('alternative-panel', $html);
            self::assertStringNotContainsString('/assets/themes/bulma.css', $html);
        }
    }

}
