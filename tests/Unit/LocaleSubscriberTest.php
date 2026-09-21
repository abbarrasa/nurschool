<?php

namespace Nurschool\Tests\Unit;

use Nurschool\EventSubscriber\LocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocaleSubscriberTest extends TestCase
{
    public function testLocalesComeFromConfigurationAndPreferenceCookieIsSecureOnHttps(): void
    {
        $switcher = new LocaleSwitcher('fr', []);
        $subscriber = new LocaleSubscriber(['fr', 'de'], 'fr', $switcher);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('https://localhost/login?_locale=de');
        $subscriber->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame('de', $request->getLocale());
        self::assertSame('de', $switcher->getLocale());
        $response = new Response();
        $response->setVary('Origin');
        $subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertSame('de', $response->headers->get('Content-Language'));
        self::assertContains('Origin', $response->getVary());
        self::assertContains('Cookie', $response->getVary());
        $cookie = $response->headers->getCookies()[0];
        self::assertSame('de', $cookie->getValue());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertGreaterThan(time(), $cookie->getExpiresTime());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testSubrequestsDoNotChangeTheLocaleOrWritePreferenceCookies(): void
    {
        $switcher = new LocaleSwitcher('es', []);
        $subscriber = new LocaleSubscriber(['es', 'en'], 'es', $switcher);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/login?_locale=en');
        $request->setLocale('es');
        $subscriber->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST));
        self::assertSame('es', $switcher->getLocale());
        self::assertSame('es', $request->getLocale());
        $response = new Response();
        $subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response));
        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Content-Language'));
    }
}
