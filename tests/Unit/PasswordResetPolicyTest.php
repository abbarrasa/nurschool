<?php

namespace Nurschool\Tests\Unit;

use Nurschool\PasswordReset\PasswordResetPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordResetPolicyTest extends TestCase
{
    /** @dataProvider invalidLifetimes */
    public function testRejectsNonPositiveLifetime(int $ttl): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PasswordResetPolicy($ttl);
    }

    public static function invalidLifetimes(): iterable
    {
        yield [0];
        yield [-1];
    }
}
