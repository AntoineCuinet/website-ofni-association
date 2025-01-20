<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Form\AddPhotoFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class GalerieController extends AbstractController
{
    #[Route('/photos', name: 'galerie')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/photos')] string $photosDirectory
    ): Response {
        if (!$this->isGranted('ROLE_ETUDIANT')) {
            return $this->render('galerie/access_not_granted.html.twig');
        }

        $to_display = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $first_photo = new Photo();
            $add_form = $this->createForm(AddPhotoFormType::class, $first_photo);
            $add_form->handleRequest($request);
            $to_display["add_form"] = $add_form;

            if ($add_form->isSubmitted() && $add_form->isValid()) {
                $photo_files = $add_form['photos']->getData();
                foreach ($photo_files as $p) {
                    $photo = new Photo();
                    $originalFilename = pathinfo($p->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $p->guessExtension();

                    try {
                        $p->move($photosDirectory, $newFilename);
                    } catch (FileException $e) {
                        // Handle errors during file upload
                        $this->addFlash("error", "Un problème a été rencontré lors de l'envoi des images.");
                        return $this->render('galerie/index.html.twig', $to_display);
                    }

                    $photo->setEvent($add_form["event"]->getData());
                    $photo->setYear($add_form["year"]->getData());
                    $photo->setUri('uploads/photos/' . $newFilename);
                    //$photo->setUri($newFilename);
                    $entityManager->persist($photo);
                }
                $entityManager->flush();

                $this->addFlash("success", "Les images ont bien été uploadées.");
                return $this->redirectToRoute('galerie');
            }
        }

        // Fetch data for display
        $to_display["events"] = $this->fetchPhotosData($entityManager);

        return $this->render('galerie/index.html.twig', $to_display);
    }

    /**
     * Fetch photo data from the database and organize it for display.
     */
    private function fetchPhotosData(EntityManagerInterface $entityManager): array
    {
        $photos = $entityManager->getRepository(Photo::class)->findAll();

        $photos_to_display = [];

        foreach ($photos as $photo) {
            $year = $photo->getYear();
            $event = $photo->getEvent();
            if (!array_key_exists($year, $photos_to_display)) {
                $photos_to_display[$year] = [];
            }
            if (!array_key_exists($event, $photos_to_display[$year])) {
                $photos_to_display[$year][$event] = [];
            }
            $photos_to_display[$year][$event][] = [
                "uri" => $photo->getUri(),
                "id" => $photo->getId()
            ];
        }

        return $photos_to_display;
    }

    #[Route('/photos/delete/{id}', name: 'delete_photo', methods: ['POST'])]
    public function deletePhoto(int $id, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $photo = $entityManager->getRepository(Photo::class)->find($id);

        if (!$photo) {
            $this->addFlash('error', 'La photo n\'existe pas.');
            return $this->redirectToRoute('galerie');
        }

        if ($this->isCsrfTokenValid('delete' . $photo->getId(), $request->request->get('_token'))) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $photo->getUri();

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $entityManager->remove($photo);
            $entityManager->flush();

            $this->addFlash('success', 'La photo a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('galerie');
    }

}

