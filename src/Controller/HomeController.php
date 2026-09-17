<?php

namespace Nurschool\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

final class HomeController
{
    #[Route('/', name: 'nurschool_home', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Environment $twig): Response
    {
        return new Response($twig->render('home/index.html.twig'), 200, ['Cache-Control' => 'no-store, private']);
    }
}
