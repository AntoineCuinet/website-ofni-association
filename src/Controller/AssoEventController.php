<?php

namespace App\Controller;

use App\Entity\AssoEvent;
use App\Entity\AssoEventInstance;
use App\Form\AssoEventInstanceType;
use App\Form\AssoEventType;
use App\Repository\AssoEventInstanceRepository;
use App\Repository\AssoEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AssoEventController extends AbstractController
{
    public static string $DEFAULT_IMAGE_NAME = 'event_default.png';

    #[Route('/events', name: 'event.index')]
    public function index(AssoEventRepository $repository): Response
    {
        // page rendering
        return $this->render('asso_event/index.html.twig', [
            'events' => $repository->findAll()
        ]);
    }

    #[Route('/events/{slug}-{id}', name: 'event.show', requirements: ['slug' => '[a-z0-9\-]+', 'id' => '\d+'])]
    public function show(AssoEvent $event, string $slug): Response
    {
        // slug correction
        if ($event->getSlug() !== $slug) {
            return $this->redirectToRoute('event.show', [
                'id' => $event->getId(),
                'slug' => $event->getSlug()
            ], 301);
        }
        // page rendering
        return $this->render('asso_event/show.html.twig', [
            'event' => $event
        ]);
    }

    #[Route('/events/create', name: 'event.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        // form create and handling
        $event = new AssoEvent();
        $form = $this->createForm(AssoEventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // save event in database
            $em->persist($event);
            $em->flush();
            return $this->redirectToRoute('event.index');
        }
        // page rendering
        return $this->render('asso_event/create.html.twig', [
            'form' => $form
        ]);
    }

    #[Route('/events/{id}/edit', name: 'event.edit', requirements: ['id' => '\d+'])]
    public function edit(AssoEvent $event, Request $request, EntityManagerInterface $em): Response
    {
        // form create and handling
        $form = $this->createForm(AssoEventType::class, $event, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // save event in database
            $em->flush();
            return $this->redirectToRoute('event.index');
        }
        // page rendering
        return $this->render('asso_event/edit.html.twig', [
            'event' => $event,
            'form' => $form
        ]);
    }

    #[Route('/events/instance', name: 'event.instance.index')]
    function instanceIndex(AssoEventInstanceRepository $repository): Response
    {
        // page rendering
        return $this->render('asso_event/instance_index.html.twig', [
            'prev' => $repository->prev(),
            'next' => $repository->next()
        ]);
    }

    #[Route('/events/instance/{slug}-{id}', name: 'event.instance.show', requirements: ['slug' => '[a-z0-9\-]+', 'id' => '\d+'])]
    public function instanceShow(AssoEventInstance $instance, string $slug): Response
    {
        // slug correction
        if ($instance->getSlug() !== $slug) {
            return $this->redirectToRoute('event.instance.show', [
                'id' => $instance->getId(),
                'slug' => $instance->getSlug()
            ], 301);
        }
        // page rendering
        return $this->render('asso_event/instance_show.html.twig', [
            'instance' => $instance
        ]);
    }

    #[Route('/events/instance/create', name: 'event.instance.create')]
    public function instanceCreate(Request $request, EntityManagerInterface $em): Response
    {
        // form create and handling
        $instance = new AssoEventInstance();
        $form = $this->createForm(AssoEventInstanceType::class, $instance);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($instance);
            $em->flush();
            return $this->redirectToRoute('event.instance.show', [
                'id' => $instance->getId(),
                'slug' => $instance->getSlug()
            ]);
        }
        // page rendering
        return $this->render('asso_event/instance_create.html.twig', [
            'form' => $form
        ]);
    }

    #[Route('/events/instance/{id}/edit', name: 'event.instance.edit', requirements: ['id' => '\d+'])]
    public function instanceEdit(AssoEventInstance $instance, Request $request, EntityManagerInterface $em): Response
    {
        // form create and handling
        $form = $this->createForm(AssoEventInstanceType::class, $instance);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('event.instance.show', [
                'id' => $instance->getId(),
                'slug' => $instance->getSlug()
            ]);
        }
        //  page rendering
        return $this->render('asso_event/instance_edit.html.twig', [
            'instance' => $instance,
            'form' => $form
        ]);
    }

    #[Route('/events/instance/{id}/delete', name: 'event.instance.delete', requirements: ['id' => '\d+'])]
    public function instanceDelete(AssoEventInstance $instance, EntityManagerInterface $em): Response
    {
        $em->remove($instance);
        $em->flush();
        return $this->redirectToRoute('home');
    }

    #[Route('/events/instance/admin', name: 'event.instance.admin')]
    public function instanceAdmin(AssoEventInstanceRepository $repository): Response
    {
        // page rendering
        return $this->render('asso_event/instance_admin.html.twig', [
            'instances' => $repository->history()
        ]);
    }
}
