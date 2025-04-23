<?php

namespace App\Controller;

use App\Entity\FormWidget;
use App\Form\FormWidgetViewType;
use App\Repository\FormWidgetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FormController extends AbstractController
{
    #[Route('/inscription/event-{id}', name: 'inscription', requirements: ['id' => '\d+', 'slug' => '[a-z0-9-]+'])]
    public function inscription(FormWidget $widget, FormWidgetRepository $formRepo): Response
    {
        // slug correction
        // if ($widget->getSlug() !== $slug) {
        //     return $this->redirectToRoute('inscription', [
        //         'id' => $widget->getId(),
        //         'slug' => $widget->getSlug()
        //     ], 301);
        // }
        // form creation
        $formBuilder = $this->createFormBuilder();
        (new FormWidgetViewType($formRepo))->buildForm($formBuilder, ['widget' => $widget]);
        $form = $formBuilder->getForm();
        // page rendering
        return $this->render('form/inscription.html.twig', [
            'form' => $form,
        ]);
    }
}
