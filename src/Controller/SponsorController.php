<?php

namespace App\Controller;

use App\Entity\Sponsor;
use App\Form\SponsorLogoType;
use App\Form\SponsorType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SponsorController extends AbstractController
{
    #[Route('/admin/sponsor/create', name: 'admin.sponsor.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        // generate form
        $sponsor = new Sponsor();
        $form = $this->createForm(SponsorType::class, $sponsor);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // save to database
            $em->persist($sponsor);
            $em->flush();
            // redirect to admin page
            return $this->redirectToRoute('admin.sponsor');
        }
        // rendering page
        return $this->render('sponsor/form.html.twig', [
            'is_edit' => false,
            'form' => $form,
        ]);
    }

    #[Route('/admin/sponsor/{id}/edit', name: 'admin.sponsor.edit', requirements: ['id' => '\d+'])]
    public function edit(Sponsor $sponsor, Request $request, EntityManagerInterface $em): Response
    {
        // generate form
        $form = $this->createForm(SponsorType::class, $sponsor, ['is_edit' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // save to database
            $em->flush();
            // redirect to admin page
            return $this->redirectToRoute('admin.sponsor');
        }
        // rendering page
        return $this->render('sponsor/form.html.twig', [
            'is_edit' => true,
            'form' => $form,
        ]);
    }

    #[Route('/admin/sponsor/{id}/edit/logo', name: 'admin.sponsor.edit.logo', requirements: ['id' => '\d+'])]
    public function editLogo(Sponsor $sponsor, Request $request, EntityManagerInterface $em): Response
    {
        // generate form
        $form = $this->createForm(SponsorLogoType::class, $sponsor);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // save to database
            $em->flush();
            // redirect to admin page
            return $this->redirectToRoute('admin.sponsor');
        }
        // rendering page
        return $this->render('sponsor/form_logo.html.twig', [
            'form' => $form,
            'sponsor' => $sponsor,
        ]);
    }

    #[Route('/admin/sponsor/{id}/edit/logo/delete', name: 'admin.sponsor.edit.logo.delete', requirements: ['id' => '\d+'])]
    public function deleteLogo(Sponsor $sponsor, EntityManagerInterface $em): Response
    {
        // delete logo
        $sponsor->setLogo(null);
        $em->flush();
        // redirect to admin page
        return $this->redirectToRoute('admin.sponsor');
    }

    #[Route('/admin/sponsor/{id}/delete', name: 'admin.sponsor.delete', requirements: ['id' => '\d+'])]
    public function delete(Sponsor $sponsor, EntityManagerInterface $em): Response
    {
        // delete sponsor
        $sponsor->setLogo(null);
        $em->remove($sponsor);
        $em->flush();
        // redirect to admin page
        return $this->redirectToRoute('admin.sponsor');
    }
}
