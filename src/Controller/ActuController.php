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

    #[Route('/admin/actu/create', name: 'admin.actu.create')]
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

    #[Route('/admin/actu/{id}/delete', name: 'admin.actu.delete', requirements: ['id' => '\d+'])]
    public function delete(Actu $actu, Request $request, EntityManagerInterface $em): Response
    {
        $actu->setImage(null);
        $em->remove($actu);
        $em->flush();
        return $this->redirectToRoute('actu.admin');
    }

    #[Route('/admin/actu/delete', name: 'admin.actu.delete.all')]
    public function deleteAll(ActuRepository $repository, EntityManagerInterface $em): Response
    {
        $actus = $repository->findAll();
        foreach ($actus as $actu) {
            $actu->setImage(null);
            $em->remove($actu);
        }
        $em->flush();
        return $this->redirectToRoute('admin.actu');
    }

    #[Route('/admin/actu/delete-{nbrPreservedMonths}', name: 'admin.actu.delete.old', requirements: ['nbrPreservedMonths' => '\d+'])]
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
}
