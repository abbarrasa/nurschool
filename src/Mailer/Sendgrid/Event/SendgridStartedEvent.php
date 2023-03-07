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

final class SendgridStartedEvent extends Event
{
    private Mail $mail;

    public function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    public function getMail(): Mail
    {
        return $this->mail;
    }
}