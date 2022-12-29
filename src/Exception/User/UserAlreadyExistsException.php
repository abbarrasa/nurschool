<?php

declare(strict_types=1);

namespace Nurschool\Exception\User;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserAlreadyExistsException extends ConflictHttpException
{
    public static function fromEmail(string $email): self
    {
        throw new self(\sprintf("User with email '%s' already exists.", $email));
    }
}