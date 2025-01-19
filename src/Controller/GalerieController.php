<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Form\AddPhotoFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GalerieController extends AbstractController {

    #[Route('/photos', name: 'galerie')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {


        $to_display = [
            'events' => [
                '2024' => [
                    'Nuit de l\'info' => [
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400',
                        'https://placehold.co/600x400'
                    ]
                ]
            ]
        ];

        if ($this->isGranted('ROLE_ADMIN')) {
            $photo = new Photo();
            $add_form = $this->createForm(AddPhotoFormType::class, $photo);
            $add_form->handleRequest($request);
            $to_display["add_form"] = $add_form;

            if ($add_form->isSubmitted() && $add_form->isValid()) {
                $entityManager->persist($photo);
                $entityManager->flush();
            }

        }

        return $this->render('galerie/index.html.twig', $to_display);
    }
}
