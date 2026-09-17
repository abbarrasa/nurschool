<?php

namespace Nurschool\Validation;

final class SuperAdminCredentialsValidator
{
    public function email(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Introduce un email válido.');
        }
        $email = trim($value);
        if (strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Introduce un email válido de hasta 180 caracteres.');
        }

        return $email;
    }

    public function password(#[\SensitiveParameter] mixed $value): string
    {
        // Keep the password within bcrypt's byte limit, including on installations using auto hashing.
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) < 12 || strlen($value) > 72) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos 12 caracteres y como máximo 72 bytes.');
        }

        return $value;
    }
}
