<?php

namespace Nurschool\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Nurschool\ApiResource\CurrentUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** @implements ProviderInterface<CurrentUser> */
final readonly class CurrentUserProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrentUser
    {
        $user = $this->security->getUser();
        if ($user === null) {
            throw new AccessDeniedException();
        }
        return new CurrentUser($user->getUserIdentifier());
    }
}
