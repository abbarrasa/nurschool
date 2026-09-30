<?php

namespace Nurschool\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

final class PasswordResetConfigurationTest extends KernelTestCase
{
    public function testNamedResetLockFactoryCanAcquireAndReleaseLock(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('lock.password_reset_requests.factory');
        self::assertInstanceOf(LockFactory::class, $factory);
        $lock = $factory->createLock('password-reset-configuration-test');
        try {
            self::assertTrue($lock->acquire());
        } finally {
            $lock->release();
        }
        self::assertFalse($lock->isAcquired());
    }
}
