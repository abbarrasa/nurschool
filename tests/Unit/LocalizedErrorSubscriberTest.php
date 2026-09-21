<?php

namespace Nurschool\Tests\Unit;

use Nurschool\EventSubscriber\LocalizedErrorSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class LocalizedErrorSubscriberTest extends TestCase
{
    /** @dataProvider errors */
    public function testApiErrorsTranslateSafeKeysAndPreserveStatusAndHeaders(int $status, string $key): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')->with($key, [], null, 'en')->willReturn('Safe translated message');
        $subscriber = new LocalizedErrorSubscriber($translator, new Environment(new ArrayLoader()));
        $request = Request::create('/api/example');
        $request->setLocale('en');
        $exception = $status === 500 ? new \RuntimeException('Confidential diagnostics')
            : new HttpException($status, 'Confidential diagnostics', headers: ['Retry-After' => '60']);
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        $subscriber->onException($event);
        self::assertSame($status, $event->getResponse()->getStatusCode());
        self::assertSame(['message' => 'Safe translated message'], json_decode($event->getResponse()->getContent(), true));
        self::assertTrue($event->getResponse()->headers->hasCacheControlDirective('no-store'));
        if ($status !== 500) {
            self::assertSame('60', $event->getResponse()->headers->get('Retry-After'));
        }
    }

    public static function errors(): array
    {
        return [[400, 'error.400'], [401, 'error.401'], [403, 'error.403'], [404, 'error.404'],
            [405, 'error.405'], [415, 'error.415'], [422, 'error.422'], [429, 'error.429'], [500, 'error.generic']];
    }
}
