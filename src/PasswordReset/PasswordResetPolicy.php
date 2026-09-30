<?php

namespace Nurschool\PasswordReset;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PasswordResetPolicy
{
    public function __construct(
        #[Autowire('%env(int:PASSWORD_RESET_TTL)%')] public int $ttl,
    ) {
        if ($ttl < 1) {
            throw new \InvalidArgumentException('Password reset lifetime must be a positive number of seconds.');
        }
    }
}
