<?php

namespace App\Controller;

use App\Form\ContactFormType;
use App\Repository\AssoEventInstanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(AssoEventInstanceRepository $instanceRepo): Response
    {
        $instances = $instanceRepo->next(2);
        $contact_form = $this->createForm(ContactFormType::class);
        return $this->render('home/index.html.twig', [
            'instances' => $instances,
            'contact_form' => $contact_form,
        ]);
    }
}
