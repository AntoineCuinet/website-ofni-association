<?php

namespace App\Controller;

use App\Entity\Board;
use App\Form\BoardType;
use App\Form\ContactFormType;
use App\Repository\BoardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PresentationController extends AbstractController
{
    #[Route('/presentation', name: 'presentation', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {

        $board = new Board();
        $board_form = $this->createForm(BoardType::class, $board);
        $board_form->handleRequest($request);
        $to_display = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $to_display['board_form'] = $board_form;

            if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
                $data = json_decode($request->getContent(), true);

                error_log(print_r($data, true));

                $board->setYearStart($data["board[year_start]"]);
                error_log("here");
                $board->setYearEnd($data['board[year_end]']);
                $board->setPresident($data['board[president]']);
                $board->setTresorier($data['board[tresorier]']);
                $board->setSecretaire($data['board[secretaire]']);


                if (isset($data['members'])) {
                    $others = [];
                    foreach ($data['members'] as $member) {
                        $others[$member['role']] = $member['name'];
                    }
                    $board->setOthers($others); // Store as JSON
                }

                error_log(print_r($board, true));

                $entityManager->persist($board);
                $entityManager->flush();
                return new JsonResponse(['status' => 'success']);

            }

            if ($board_form->isSubmitted() && $board_form->isValid()) {
                $entityManager->persist($board);
                $entityManager->flush();
                return $this->redirectToRoute('presentation');
            }


        }

        $boards = $entityManager->getRepository(Board::class)->findBy([], ["year_start" => "DESC"]);
        $to_display["boards"] = [];
        foreach ($boards as $board) {
            $to_display_tmp = [];
            $to_display_tmp["years"] = strval($board->getYearStart()) . '-' . strval($board->getYearEnd());
            $to_display_tmp["president"] = $board->getPresident();
            $to_display_tmp["tresorier"] = $board->getTresorier();
            $to_display_tmp["secretaire"] = $board->getSecretaire();
            // TODO: add a way to display other membres


            $others = $board->getOthers();
            if ($others) {
                $to_display_tmp["others"] = $others;
            }

            $to_display["boards"][] = $to_display_tmp;
        }
        return $this->render('presentation/index.html.twig', $to_display);
    }
}
