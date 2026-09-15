<?php

namespace Nurschool\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    #[Route('/', name: 'nurschool_home', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('<h1>Welcome to Nurschool</h1>');
    }
}
