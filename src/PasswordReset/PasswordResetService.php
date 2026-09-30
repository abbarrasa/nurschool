<?php

namespace Nurschool\PasswordReset;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Nurschool\Entity\User;
use Nurschool\Mail\PasswordResetEmailSender;
use Nurschool\Repository\UserRepository;
use Nurschool\Validation\SuperAdminCredentialsValidator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class PasswordResetService
{
    public function __construct(
        private ClockInterface $clock,
        private PasswordResetPolicy $policy,
        #[Autowire(service: 'limiter.password_reset_requests')] private RateLimiterFactoryInterface $requests,
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private SuperAdminCredentialsValidator $credentials,
        private PasswordResetEmailSender $sender,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function request(array $payload, string $locale): void
    {
        try {
            $email = strtolower($this->credentials->email($payload['email'] ?? null));
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException('Invalid email address.', $exception);
        }
        // Unknown accounts consume the same quota to avoid revealing account existence.
        $limit = $this->requests->create(hash('sha256', $email))->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, 'Password reset request limit exceeded.');
        }
        $user = $this->users->findOneBy(['email' => $email]);
        if ($user === null) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $user->requirePasswordReset(hash('sha256', $token), $this->clock->now()->modify('+'.$this->policy->ttl.' seconds'));
        // The reset token and Doctrine email queue share one transaction.
        $this->em->wrapInTransaction(function () use ($email, $token, $locale): void {
            $this->em->flush();
            $this->sender->send($email, $token, $locale);
        });
    }

    /** @param array<string, mixed> $payload */
    public function reset(#[\SensitiveParameter] array $payload): void
    {
        try {
            $password = $this->credentials->password($payload['password'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidPasswordException('Invalid password.', $exception);
        }
        $token = $payload['token'] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new UnprocessableEntityHttpException('Invalid password reset token.');
        }
        $hash = $this->hasher->hashPassword(new User(), $password);
        if ($this->users->consumePasswordResetToken($token, $hash, $this->clock->now()) !== 1) {
            throw new UnprocessableEntityHttpException('Password reset token is expired or consumed.');
        }
    }
}
