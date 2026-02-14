<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/integration')]
class IntegrationController extends AbstractController
{
    #[Route('', name: 'app_integration')]
    public function index(): Response
    {
        return $this->render('integration/index.html.twig', [
            'controller_name' => 'IntegrationController',
        ]);
    }

    #[Route('/visual', name: 'app_integration_visual')]
    public function visual(): Response
    {
        return $this->render('integration/visual.html.twig', [
            'controller_name' => 'IntegrationController',
        ]);
    }

    #[Route('/welcome', name: 'app_integration_welcome')]
    public function welcome(): Response
    {
        return $this->render('integration/welcome.html.twig');
    }
}
