<?php

namespace Nurschool\Repository;

use Nurschool\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }


    public function consumeVerificationToken(string $token): int
    {
        return $this->getEntityManager()->createQuery('UPDATE Nurschool\\Entity\\User u SET u.verified = true, u.verificationHash = NULL, u.verificationExpiresAt = NULL WHERE u.verificationHash = :hash AND u.verified = false AND u.verificationExpiresAt > :now')
            ->setParameter('hash', hash('sha256', $token))
            ->setParameter('now', new \DateTimeImmutable())->execute();
    }
}
