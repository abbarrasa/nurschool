<?php

namespace Nurschool\Registration;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nurschool\Entity\User;
use Nurschool\Mail\VerificationEmailSender;
use Nurschool\Repository\{RoleRepository, UserRepository};
use Nurschool\Validation\SuperAdminCredentialsValidator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, UnprocessableEntityHttpException};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private RoleRepository $roles,
        private UserPasswordHasherInterface $hasher,
        private SuperAdminCredentialsValidator $credentials,
        private VerificationEmailSender $sender,
        #[Autowire('%env(int:REGISTRATION_TTL)%')] private int $ttl,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function register(#[\SensitiveParameter] array $payload, string $locale): void
    {
        try {
            $email = strtolower($this->credentials->email($payload['email'] ?? null));
            $password = $this->credentials->password($payload['password'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException('Invalid registration credentials.', $exception);
        }
        $roles = array_key_exists('roles', $payload) ? $payload['roles'] : [];
        if (!is_array($roles) || !array_is_list($roles) || count($roles) > 2) {
            throw new UnprocessableEntityHttpException('Invalid registration roles.');
        }
        foreach ($roles as $role) {
            if (!in_array($role, ['ROLE_NURSE', 'ROLE_ADMIN'], true)) {
                throw new UnprocessableEntityHttpException('Invalid registration role.');
            }
        }
        if ($this->users->findOneBy(['email' => $email]) !== null) {
            throw new ConflictHttpException('Email is already registered.');
        }
        $user = (new User())->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        foreach (array_unique(['ROLE_USER', ...$roles]) as $name) {
            $role = $this->roles->findOneBy(['name' => $name]);
            if ($role === null) {
                throw new \LogicException('Required registration role is missing. Run database migrations.');
            }
            $user->addRole($role);
        }
        if ($this->ttl < 1) {
            throw new \LogicException('Registration token lifetime must be positive.');
        }
        $token = bin2hex(random_bytes(32));
        $user->requireVerification(hash('sha256', $token), new \DateTimeImmutable('+'.$this->ttl.' seconds'));
        try {
            // The Doctrine queue shares this connection: account and email commit atomically.
            $this->em->wrapInTransaction(function () use ($user, $email, $token, $locale): void {
                $this->em->persist($user);
                $this->em->flush();
                $this->sender->send($email, $token, $locale);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('Email was registered concurrently.', $exception);
        }
    }

    public function verify(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new UnprocessableEntityHttpException('Invalid verification token.');
        }
        // A conditional update makes consumption atomic, including concurrent requests.
        $count = $this->users->consumeVerificationToken($token);
        if ($count !== 1) {
            throw new UnprocessableEntityHttpException('Verification token is expired or already consumed.');
        }
    }
}
