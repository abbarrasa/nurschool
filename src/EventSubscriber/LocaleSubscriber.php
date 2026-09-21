<?php

namespace Nurschool\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class LocaleSubscriber implements EventSubscriberInterface
{
    /** @param list<string> $enabledLocales */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')] private array $enabledLocales,
        #[Autowire('%kernel.default_locale%')] private string $defaultLocale,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $selection = $request->query->all()['_locale'] ?? null;
        $cookie = $request->cookies->all()['nurschool_locale'] ?? null;
        // API requests prefer the page's language header over a cookie changed by another tab.
        $preferred = $request->getPreferredLanguage(array_values(array_unique([$this->defaultLocale, ...$this->enabledLocales])));
        $locale = $preferred ?? $this->defaultLocale;
        $hasApiLanguage = str_starts_with($request->getPathInfo(), '/api/') && $request->getLanguages() !== [];
        if (!$hasApiLanguage && $this->isEnabled($cookie)) {
            $locale = $cookie;
        }
        if ($this->isEnabled($selection)) {
            $locale = $selection;
        }
        $request->setLocale($locale);
        $request->attributes->set('_locale', $locale);
        // Resolve before routing so even 404 and 405 responses use the selected language.
        $this->localeSwitcher->setLocale($locale);
        if ($this->isEnabled($selection)) {
            $request->attributes->set('_save_locale', $locale);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set('Content-Language', $request->getLocale());
        $response->setVary(['Accept-Language', 'Cookie'], false);
        $selection = $request->attributes->get('_save_locale');
        if (is_string($selection)) {
            $response->headers->setCookie(Cookie::create('nurschool_locale', $selection)
                ->withExpires(strtotime('+1 year'))->withSecure($request->isSecure())
                ->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_LAX));
            $response->headers->addCacheControlDirective('no-store');
        }
    }

    /** @phpstan-assert-if-true string $locale */
    private function isEnabled(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, $this->enabledLocales, true);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 90], KernelEvents::RESPONSE => ['onResponse', -10]];
    }
}
