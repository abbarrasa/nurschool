<?php

namespace Nurschool\Tests\Unit;

use Nurschool\Entity\User;
use Nurschool\Security\VerifiedUserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class VerifiedUserCheckerTest extends TestCase
{
    public function testPendingAccountIsRejected(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        (new VerifiedUserChecker())->checkPostAuth(new User());
    }

    public function testVerifiedAccountIsAccepted(): void
    {
        $user = (new User())->markVerified();
        (new VerifiedUserChecker())->checkPostAuth($user);
        self::assertTrue($user->isVerified());
    }
}
