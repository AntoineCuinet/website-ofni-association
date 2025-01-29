<?php

namespace App\Controller;

use App\Entity\Actu;
use App\Form\ActuType;
use App\Repository\ActuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ActuController extends AbstractController
{
    #[Route('/actu-{id}', name: 'actu.show', requirements: ['id' => '\d+'])]
    public function show(Actu $actu): Response
    {
        return $this->render('actu/show.html.twig', [
            'actu' => $actu
        ]);
    }

    #[Route('/actu/create', name: 'actu.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $actu = new Actu();
        $form = $this->createForm(ActuType::class, $actu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // save actu to database
            $em->persist($actu);
            $em->flush();
            return $this->redirectToRoute('actu.admin', ['id' => $actu->getId()]);
        }
        // page rendering
        return $this->render('actu/create.html.twig', [
            'form' => $form
        ]);
    }

    #[Route('/actu/{id}/delete', name: 'actu.delete', requirements: ['id' => '\d+'])]
    public function delete(Actu $actu, Request $request, EntityManagerInterface $em): Response
    {
        $actu->setImage(null);
        $em->remove($actu);
        $em->flush();
        return $this->redirectToRoute('actu.admin');
    }

    #[Route('/actu/delete-{nbrPreservedMonths}', name: 'actu.delete.old', requirements: ['nbrPreservedMonths' => '\d+'])]
    public function deleteOld(int $nbrPreservedMonths, ActuRepository $repository, EntityManagerInterface $em): Response
    {
        $date = new \DateTimeImmutable("-$nbrPreservedMonths months");
        $actus = $repository->olderThan($date);
        foreach ($actus as $actu) {
            $actu->setImage(null);
            $em->remove($actu);
        }
        $em->flush();
        return $this->redirectToRoute('actu.admin');
    }

    #[Route('/actu/admin', name: 'actu.admin')]
    public function admin(ActuRepository $repository): Response
    {
        $actus = $repository->history(0);
        return $this->render('actu/admin.html.twig', [
            'actus' => $actus
        ]);
    }
}
