<?php

declare(strict_types=1);

namespace Nurschool\Exception\User;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserNotFoundException extends NotFoundHttpException
{
    public static function fromEmail(string $email): self
    {
        throw new self(\sprintf("User with email '%s' not found.", $email));
    }
}