<?php

namespace App\Controller;

use App\Entity\AssoEventInstance;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AssoEventController extends AbstractController
{
    #[Route('/event/instance/{slug}-{id}', name: 'event.instance.show', requirements: ['slug' => '[a-z0-9\-]+', 'id' => '\d+'])]
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
        return $this->render('asso_event/instance-show.html.twig', [
            'instance' => $instance
        ]);
    }
}
