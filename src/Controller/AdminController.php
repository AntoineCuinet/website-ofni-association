<?php

namespace App\Controller;

use App\Repository\ActuRepository;
use App\Repository\AssoEventInstanceRepository;
use App\Repository\AssoEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin.index')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    #[Route('/admin/events', name: 'admin.event')]
    public function event(AssoEventRepository $eventRepo, AssoEventInstanceRepository $instanceRepo): Response
    {
        // page rendering
        return $this->render('admin/asso_event.html.twig', [
            'events' => $eventRepo->findAll(),
            'instances' => $instanceRepo->findAll()
        ]);
    }

    #[Route('/admin/actu', name: 'admin.actu')]
    public function actu(ActuRepository $repository): Response
    {
        $actus = $repository->history(0);
        return $this->render('admin/actu.html.twig', [
            'actus' => $actus
        ]);
    }
}
