<?php

declare(strict_types=1);

namespace Nurschool\Service\User;

use Exception;
use Nurschool\Entity\User;
use Nurschool\Exception\User\UserAlreadyExistsException;
use Nurschool\Repository\UserRepository;
use Nurschool\Service\Avatar\AvatarGenerator;

final class UserRegisterService
{
    private UserRepository $userRepository;
    private AvatarGenerator $avatarGenerator;

    public function __construct(
        UserRepository $userRepository,
        AvatarGenerator $avatarGenerator
    ) {
        $this->userRepository = $userRepository;
        $this->avatarGenerator = $avatarGenerator;
    }

    public function registerUserFromEmail(
        string $email,
        string $firstname,
        string $lastname
    ): User {
        $user = new User($email, $firstname, $lastname);
        $user->setAvatar($this->avatarGenerator->generateUserAvatar($user));

        try {
            $this->userRepository->save($user, true);

            return $user;
        } catch(Exception $e) {
            throw UserAlreadyExistsException::fromEmail($email);
        }
    }
}