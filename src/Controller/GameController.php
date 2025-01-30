<?php

namespace App\Controller;

use App\Entity\GameScore;
use App\Enum\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    #[Route('/game', name: 'game')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        
        $scores = $entityManager->getRepository(GameScore::class)->findBy([], ['score' => 'DESC'], 10);
        $teams_scores = $entityManager->getRepository(GameScore::class)->totalScores();

        $scores_size = count($scores);
        if ($scores_size < 3) {
            // TODO: faire quelques parties lorsque l'on déploiera le jeu.
            return $this->render('game/index.html.twig', [
                'team_first' => [
                    'name' => 'Canard',
                    'score' => 2150,
                ],
                'team_second' => [
                    'name' => 'Abeille',
                    'score' => 1000
                ],
                'first' => [
                    'name' => 'John Doe',
                    'team' =>  'Abeille',
                    'score' =>  1000
                ], 
                'second' => [
                    'name' => 'John Doe',
                    'team' =>  'Canard',
                    'score' =>  900
                ],
                'third' => [
                    'name' => 'John Doe',
                    'team' =>  'Canard',
                    'score' =>  800
                ],
                'top' => [
                    4 => [
                        'name' => 'Foobar',
                        'team' => 'Canard',
                        'score' => 450
                    ]
                ]

            ]);
        }

        $to_display = $teams_scores;
        $to_display['first'] = [
            'name' => $scores[0]->getUser()->getPseudo(),
            'team' => $scores[0]->getTeam() == Team::BEE ? 'Abeille' : 'Canard',
            'score' => $scores[0]->getScore()
        ];
        $to_display['second'] = [
            'name' => $scores[1]->getUser()->getPseudo(),
            'team' => $scores[1]->getTeam() == Team::BEE ? 'Abeille' : 'Canard',
            'score' => $scores[1]->getScore()
        ];
        $to_display['third'] = [
            'name' => $scores[2]->getUser()->getPseudo(),
            'team' => $scores[2]->getTeam() == Team::BEE ? 'Abeille' : 'Canard',
            'score' => $scores[2]->getScore()
        ];
        $to_display['top'] = [];
        for ($i = 3; $i < $scores_size; $i++) {
            $to_display['top'][$i + 1] = [
                'name' => $scores[$i]->getUser()->getPseudo(),
                'team' => $scores[$i]->getTeam() == Team::BEE ? 'Abeille' : 'Canard',
                'score' => $scores[$i]->getScore()
            ];
        }

        return $this->render('game/index.html.twig', $to_display);
    }

    #[Route('/game/save', name: 'game_save_score')]
    public function saveScore(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['message' => 'You need to be logged in to save the score'], 400);
        }

        if (!isset($data['score']) || !is_numeric($data['score'])) {
            return new JsonResponse(['message' => 'Invalid score'], 400);
        }

        if (!isset($data['team']) || ($data['team'] != 'abeille' && $data['team'] != 'canard')) {
            return new JsonResponse(['message' => 'Invalid team'], 400);
        }

        $score = (int) $data['score'];
        $team = $data['team'] == 'abeille' ? Team::BEE : Team::DUCK;

        $gameScore = new GameScore();
        $gameScore->setTeam($team);
        $gameScore->setUser($user);
        $gameScore->setScore($score);

        $entityManager->persist($gameScore);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Score saved successfully'], 200);

    }


    #[Route('/game/gameplay', name: 'game_show')]
    public function gameplay(Request $request): Response
    {
        return $this->render('game/game.html.twig');
    }
}
