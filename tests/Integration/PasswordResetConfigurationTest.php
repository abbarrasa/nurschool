<?php

namespace Nurschool\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

final class PasswordResetConfigurationTest extends KernelTestCase
{
    /** @dataProvider lockResources */
    public function testNamedResetLockFactoryCanAcquireAndReleaseLock(string $resource): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('lock.'.$resource.'.factory');
        self::assertInstanceOf(LockFactory::class, $factory);
        $lock = $factory->createLock('password-reset-configuration-test');
        try {
            self::assertTrue($lock->acquire());
        } finally {
            $lock->release();
        }
        self::assertFalse($lock->isAcquired());
    }

    public static function lockResources(): iterable
    {
        yield 'email requests' => ['password_reset_requests'];
        yield 'IP submissions' => ['password_reset_submissions'];
    }
}
