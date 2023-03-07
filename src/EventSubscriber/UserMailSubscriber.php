<?php

/*
 * This file is part of the Nurschool project.
 *
 * (c) Nurschool <https://github.com/abbarrasa/nurschool>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nurschool\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use Nurschool\Entity\User;
use Nurschool\Mailer\MailerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class UserMailSubscriber implements EventSubscriberInterface
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['sendAccountActivationMail', EventPriorities::POST_WRITE]
        ];
    }

    public function sendAccountActivationMail(ViewEvent $event): void
    {
        $object = $event->getControllerResult();
        $request = $event->getRequest();

        if ($object instanceof User &&
            $request->isMethod(Request::METHOD_POST)
        ) {
            $this->mailer->sendAccountActivationMail($object);
        }
    }
}