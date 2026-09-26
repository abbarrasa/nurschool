<?php

namespace Nurschool\Mail\SendGrid\Transport\Exception;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

/** Preserves Mailer failure events while preventing Messenger from retrying permanent failures. */
final class PermanentTransportException extends TransportException implements UnrecoverableExceptionInterface
{
}
