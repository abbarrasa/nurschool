<?php

namespace Nurschool\OAuth;

use Doctrine\ORM\EntityManagerInterface;
use Nurschool\Entity\{SocialIdentity, User};
use Nurschool\Repository\{RoleRepository, UserRepository};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AccountResolver
{
    public function __construct(private EntityManagerInterface $em, private UserRepository $users, private RoleRepository $roles, private UserPasswordHasherInterface $hasher)
    {
    }

    public function resolve(string $provider, string $subject, string $email): User
    {
        $identity = $this->em->getRepository(SocialIdentity::class)->findOneBy(['provider' => $provider, 'subject' => $subject]);
        if ($identity !== null) {
            return $identity->getUser();
        }
        // Matching email alone must never grant access to an existing local account.
        if ($this->users->findOneBy(['email' => $email]) !== null) {
            throw new \RuntimeException('An existing account requires its original sign-in method.');
        }
        $role = $this->roles->findOneBy(['name' => 'ROLE_USER']) ?? throw new \LogicException('Required user role is missing.');
        $user = (new User())->setEmail($email)->markVerified()->addRole($role);
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));
        $this->em->wrapInTransaction(function () use ($provider, $subject, $user): void {
            $this->em->persist($user);
            $this->em->persist(new SocialIdentity($provider, $subject, $user));
        });
        return $user;
    }
}
