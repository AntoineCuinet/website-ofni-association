<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    #[Route('/game', name: 'game')]
    public function index(Request $request): Response
    {
        return $this->render('game/index.html.twig');
    }


    #[Route('/game/gameplay', name: 'game_show')]
    public function gameplay(Request $request): Response
    {
        return $this->render('game/game.html.twig');
    }
}
