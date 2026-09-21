<?php

namespace Nurschool\Validation;

final class SuperAdminCredentialsValidator
{
    public function email(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Enter a valid email address.');
        }
        $email = trim($value);
        if (strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid email address of at most 180 characters.');
        }

        return $email;
    }

    public function password(#[\SensitiveParameter] mixed $value): string
    {
        // Keep the password within bcrypt's byte limit, including on installations using auto hashing.
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) < 12 || strlen($value) > 72) {
            throw new \InvalidArgumentException('The password must contain at least 12 characters and at most 72 bytes.');
        }

        return $value;
    }
}
