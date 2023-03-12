<?php

/*
 * This file is part of CoopTilleulsUrlSignerBundle.
 *
 * (c) Les-Tilleuls.coop <contact@les-tilleuls.coop>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nurschool\Service\UrlSigner\EventListener;

use Nurschool\Service\UrlSigner\Attribute\Signed;
use Nurschool\Service\UrlSigner\UrlSigner;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final class CheckSignedRouteListener implements EventSubscriberInterface
{
    private UrlSigner $urlSigner;

    public function __construct(UrlSigner $urlSigner)
    {
        $this->urlSigner = $urlSigner;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'checkSignedRoute',
        ];
    }

    public function checkSignedRoute(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->hasSignedAttribute($request)) {
            $this->urlSigner->checkRequest($request);
        }
    }

    private function hasSignedAttribute(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');
        if (\str_contains($controller, '::')) {
            list($class, $method) = \explode('::', $controller);
            $reflectionMethod = new ReflectionMethod($class, $method);
    
            return !empty($reflectionMethod->getAttributes(Signed::class));    
        }

        return false;
    }
}
