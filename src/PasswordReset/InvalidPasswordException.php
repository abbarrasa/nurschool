<?php

namespace Nurschool\PasswordReset;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvalidPasswordException extends UnprocessableEntityHttpException
{
}
