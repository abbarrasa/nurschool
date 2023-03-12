<?php

namespace Nurschool\Controller;

use Nurschool\Repository\UserRepository;
use Nurschool\Service\UrlSigner\Attribute\Signed;
use Nurschool\Service\UrlSigner\UrlSigner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    #[Route('/users/activate/{userId}', name: 'activate_user')]
    #[Signed]
    public function activate(Request $request, string $userId, UrlSigner $urlSigner): void
    {
        //http://localhost:250/users/activate/6d78cb55-6820-436a-bd63-9257c48fba26?expires=1679446101&signature=PvF4zKz%2FhYtdutUybTnaIbOMIOyBKkxPl6NHEPPJvwk%3D
        $user = $this->userRepository->find($userId);
        if (null === $user) {
            throw $this->createNotFoundException();
        }

        $a = 1;
    }
}
