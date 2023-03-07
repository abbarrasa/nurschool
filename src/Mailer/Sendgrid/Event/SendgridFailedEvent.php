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

namespace Nurschool\Mailer\Sendgrid\Event;

use SendGrid\Mail\Mail;
use Symfony\Contracts\EventDispatcher\Event;

final class SendgridFailedEvent extends Event
{
    private Mail $mail;
    private string $messageError;

    public function __construct(Mail $mail, string $messageError)
    {
        $this->mail = $mail;
        $this->messageError = $messageError;
    }

    public function getMail(): Mail
    {
        return $this->mail;
    }

    public function getMessageError(): string
    {
        return $this->messageError;
    }
}