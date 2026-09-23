<?php

namespace Nurschool\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nurschool\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class InternationalizationTest extends WebTestCase
{
    public function testSpanishIsTheDefaultAndVueReceivesTranslatedPresentationMessages(): void
    {
        $browser = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => '']);
        $crawler = $browser->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Language', 'es');
        self::assertSelectorExists('html[lang="es"]');
        self::assertSelectorTextContains('h1', 'Iniciar sesión');
        self::assertSelectorTextContains('label[for="email"]', 'Correo electrónico');
        self::assertSelectorExists('[data-locale="es"][aria-current="true"]');
        $messages = json_decode($crawler->filter('#login-app')->attr('data-messages'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Entrando…', $messages['login.submitting']);
        self::assertSame('Email o contraseña incorrectos.', $messages['login.error.credentials']);
        self::assertContains('Accept-Language', $browser->getResponse()->getVary());
        self::assertContains('Cookie', $browser->getResponse()->getVary());
    }

    public function testLanguageSelectionPersistsAcrossNavigationAndCanBeChanged(): void
    {
        $browser = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => '']);
        $browser->request('GET', '/login');
        $crawler = $browser->clickLink('English');
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('h1', 'Sign in');
        self::assertSelectorExists('[data-locale="en"][aria-current="true"]');
        $messages = json_decode($crawler->filter('#login-app')->attr('data-messages'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Signing in…', $messages['login.submitting']);
        self::assertSame('en', $browser->getCookieJar()->get('nurschool_locale')->getValue());
        self::assertTrue($browser->getCookieJar()->get('nurschool_locale')->isHttpOnly());
        $browser->request('GET', '/login');
        self::assertResponseHeaderSame('Content-Language', 'en');
        $browser->clickLink('Español');
        self::assertResponseHeaderSame('Content-Language', 'es');
    }

    public function testUnsupportedAndMalformedSelectionsDoNotOverwritePreferences(): void
    {
        $browser = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => '']);
        foreach (['fr', ['en'], '<script>'] as $locale) {
            $browser->request('GET', '/login', ['_locale' => $locale]);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Language', 'es');
            self::assertNull($browser->getCookieJar()->get('nurschool_locale'));
        }
        $browser->getCookieJar()->set(new Cookie('nurschool_locale', 'unsupported'));
        $browser->request('GET', '/login');
        self::assertResponseHeaderSame('Content-Language', 'es');
        $browser->request('GET', '/login?_locale=en');
        $browser->request('GET', '/login?_locale=fr');
        self::assertResponseHeaderSame('Content-Language', 'en');
    }

    public function testBrowserLanguageNegotiationAndApiPageLanguagePrecedence(): void
    {
        $browser = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => '']);
        $browser->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr;q=1,en-GB;q=0.9,es;q=0.5']);
        self::assertResponseHeaderSame('Content-Language', 'en');
        $browser->request('GET', '/login?_locale=es');
        $browser->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        self::assertResponseHeaderSame('Content-Language', 'es');
        $browser->request('GET', '/api/me', server: ['HTTP_ACCEPT_LANGUAGE' => 'en', 'HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Language', 'en');
        self::assertSame(['message' => 'You must sign in.'], json_decode($browser->getResponse()->getContent(), true));
    }

    /** @dataProvider locales */
    public function testLocalizedLoginErrorsAndFullSessionLifecycle(string $locale, string $title, string $logout): void
    {
        $browser = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => '']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $user = (new User())->markVerified()->setEmail('locale@example.com')->setPassword(password_hash('correct-password', PASSWORD_BCRYPT, ['cost' => 4]));
        $em->persist($user);
        $em->flush();
        $browser->request('GET', '/login?_locale='.$locale);
        $catalog = json_decode(file_get_contents(dirname(__DIR__, 2).'/translations/messages.'.$locale.'.json'), true);
        foreach ([['locale@example.com', 'wrong'], ['missing@example.com', 'wrong']] as [$email, $password]) {
            $browser->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => $password]);
            self::assertResponseStatusCodeSame(401);
            self::assertSame(['message' => $catalog['login.error.credentials']], json_decode($browser->getResponse()->getContent(), true));
        }
        foreach (['{}', '{broken', '{"email": [], "password": true}'] as $payload) {
            $browser->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
            self::assertResponseStatusCodeSame(400);
            self::assertSame(['message' => $catalog['login.error.validation']], json_decode($browser->getResponse()->getContent(), true));
        }
        $browser->request('POST', '/api/login');
        self::assertResponseStatusCodeSame(415);
        self::assertSame(['message' => $catalog['error.415']], json_decode($browser->getResponse()->getContent(), true));
        $browser->jsonRequest('POST', '/api/login', ['email' => 'locale@example.com', 'password' => 'correct-password']);
        self::assertResponseIsSuccessful();
        $crawler = $browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
        self::assertStringNotContainsString('locale@example.com', $browser->getResponse()->getContent());
        $messages = json_decode($crawler->filter('#home-app')->attr('data-messages'), true);
        self::assertSame($catalog['home.error.network'], $messages['home.error.network']);
        $browser->request('POST', '/logout', ['_csrf_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('[role="alert"]', $catalog['error.403']);
        $crawler = $browser->request('GET', '/');
        $browser->submit($crawler->selectButton($logout)->form());
        self::assertResponseRedirects('/login');
        $browser->followRedirect();
        self::assertResponseHeaderSame('Content-Language', $locale);
        self::assertSelectorTextContains('h1', $catalog['login.title']);
        $browser->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['message' => $catalog['error.401']], json_decode($browser->getResponse()->getContent(), true));
    }

    /** @dataProvider locales */
    public function testRoutingErrorsAreTranslatedWithoutLeakingInternalDetails(string $locale): void
    {
        $browser = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => '']);
        $browser->request('GET', '/missing?_locale='.$locale);
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorExists('html[lang="'.$locale.'"]');
        $catalog = json_decode(file_get_contents(dirname(__DIR__, 2).'/translations/messages.'.$locale.'.json'), true);
        self::assertSelectorTextContains('[role="alert"]', $catalog['error.404']);
        $browser->request('GET', '/api/login');
        self::assertResponseStatusCodeSame(405);
        self::assertSame(['message' => $catalog['error.405']], json_decode($browser->getResponse()->getContent(), true));
        self::assertStringContainsString('POST', $browser->getResponse()->headers->get('Allow'));
        self::assertStringNotContainsString('trace', $browser->getResponse()->getContent());
    }

    public static function locales(): array
    {
        return [['es', 'Bienvenido a Nurschool', 'Salir'], ['en', 'Welcome to Nurschool', 'Sign out']];
    }
}
