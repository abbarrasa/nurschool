<?php

namespace Nurschool\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class LoginController
{
    #[Route('/login', name: 'nurschool_login', methods: ['GET'])]
    public function __invoke(Environment $twig): Response
    {
        return new Response($twig->render('security/login.html.twig'));
    }
}
