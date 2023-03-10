<?php

/*
 * This file is part of the Nurschool project.
 *
 * (c) Nurschool <https://github.com/abbarrasa/nurschool>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nurschool\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Nurschool\Entity\User;
use Nurschool\Service\Avatar\AvatarGeneratorInterface;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: User::class)]
class UserAvatarSetter
{
    private AvatarGeneratorInterface $avatarGenerator;

    public function __construct(AvatarGeneratorInterface $avatarGenerator)
    {
        $this->avatarGenerator = $avatarGenerator;    
    }

    public function prePersist(User $user, LifecycleEventArgs $event): void
    {
        if (null === $user->getAvatar()) {
            $avatar = $this->avatarGenerator->generateUserAvatar($user);
            $user->setAvatar($avatar->path());
        }
    }
}