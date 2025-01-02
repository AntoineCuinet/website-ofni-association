<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ErrorController extends AbstractController
{
    #[Route('/error/{code}', name: 'error_handler')]
    public function showError(int $code): Response
    {
        // Détermine le message en fonction du code d'erreur
        $messages = [
            404 => 'Page non trouvée',
            500 => 'Erreur de serveur',
            403 => 'Accès refusé'
        ];

        $message = $messages[$code] ?? 'Un problème est survenu';

        // Rendre une seule page Twig avec les détails de l'erreur
        return $this->render('error/index.html.twig', [
            'code' => $code,
            'message' => $message,
        ], new Response('', $code));
    }

    /**
     * Cette méthode est utilisée pour attraper toutes les erreurs non gérées.
     */
    public function handleError(HttpExceptionInterface $exception): Response
    {
        $code = $exception->getStatusCode();
        return $this->showError($code);
    }
}
