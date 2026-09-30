<?php

namespace Nurschool\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Nurschool\Entity\User;
use Nurschool\Mail\PasswordResetEmailSender;
use Nurschool\PasswordReset\{PasswordResetPolicy, PasswordResetService};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Nurschool\Repository\UserRepository;
use Nurschool\Validation\SuperAdminCredentialsValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetServiceTest extends TestCase
{
    /** @dataProvider lifetimes */
    public function testRequestStoresOnlyHashAndUsesConfiguredLifetime(int $ttl): void
    {
        $clock = new MockClock('2026-09-30 12:00:00 UTC');
        $user = (new User())->setEmail('ana@example.com');
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('findOneBy')->with(['email' => 'ana@example.com'])->willReturn($user);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('wrapInTransaction')->willReturnCallback(static function (callable $operation): void { $operation(); });
        $em->expects(self::once())->method('flush');
        $sender = $this->createMock(PasswordResetEmailSender::class);
        $sender->expects(self::once())->method('send')->with('ana@example.com', self::callback(static function (string $token) use ($user, $clock, $ttl): bool {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $token);
            $hash = new \ReflectionProperty(User::class, 'passwordResetHash');
            self::assertSame(hash('sha256', $token), $hash->getValue($user));
            $expiry = new \ReflectionProperty(User::class, 'passwordResetExpiresAt');
            self::assertEquals($clock->now()->modify('+'.$ttl.' seconds'), $expiry->getValue($user));
            return true;
        }), 'en');
        $service = new PasswordResetService($clock, new PasswordResetPolicy($ttl), new RateLimiterFactory(['id' => 'reset', 'policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'], new InMemoryStorage()), $em, $users, $this->createMock(UserPasswordHasherInterface::class), new SuperAdminCredentialsValidator(), $sender);
        $service->request(['email' => ' ANA@example.com '], 'en');
    }
    public static function lifetimes(): iterable
    {
        yield 'default one hour' => [3600];
        yield 'fifteen minutes' => [900];
        yield 'two hours' => [7200];
    }

    public function testCustomLimitAppliesToUnknownEmailsAndResetsAfterWindow(): void
    {
        $clock = new MockClock('2026-09-30 12:00:00 UTC');
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::exactly(3))->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('wrapInTransaction');
        $sender = $this->createMock(PasswordResetEmailSender::class);
        $sender->expects(self::never())->method('send');
        $storage = new InMemoryStorage();
        $limiter = new RateLimiterFactory(['id' => 'reset', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 hour'], $storage);
        $service = new PasswordResetService($clock, new PasswordResetPolicy(900), $limiter, $em, $users, $this->createMock(UserPasswordHasherInterface::class), new SuperAdminCredentialsValidator(), $sender);
        $service->request(['email' => 'unknown@example.com'], 'en');
        $service->request(['email' => ' UNKNOWN@example.com '], 'en');
        try {
            $service->request(['email' => 'unknown@example.com'], 'en');
            self::fail('The third request must exceed the custom limit of two.');
        } catch (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $exception) {
            self::assertGreaterThan(0, (int) $exception->getHeaders()['Retry-After']);
        }
        // Simulate a completed window without sleeping or replacing the limiter.
        $window = new \Symfony\Component\RateLimiter\Policy\Window('reset-'.hash('sha256', 'unknown@example.com'), 3600, 2, microtime(true) - 3601);
        $window->add(2, microtime(true) - 3601);
        $storage->save($window);
        $service->request(['email' => 'unknown@example.com'], 'en');
    }
}
