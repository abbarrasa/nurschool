<?php

namespace Nurschool\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class PasswordResetController
{
    #[Route('/forgot-password', name: 'nurschool_forgot_password', methods: ['GET'])]
    #[Route('/reset-password', name: 'nurschool_reset_password', methods: ['GET'])]
    public function __invoke(Environment $twig): Response
    {
        return new Response($twig->render('security/password_reset.html.twig'), headers: [
            'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
