<?php

namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/legal', name: 'app_legal')]
    public function index(Request $request): Response
    {
        $contact_form = $this->createForm(ContactFormType::class);
        return $this->render('legal/index.html.twig', [
            'contact_form' => $contact_form,
        ]);
    }
}
