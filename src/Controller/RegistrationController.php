<?php

namespace Nurschool\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class RegistrationController
{
    #[Route('/register', name: 'nurschool_register', methods: ['GET'])]
    #[Route('/verify-account', name: 'nurschool_verify_account', methods: ['GET'])]
    public function __invoke(Environment $twig): Response
    {
        return new Response($twig->render('security/register.html.twig'), headers: [
            'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
