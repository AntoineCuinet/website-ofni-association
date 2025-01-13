<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ErrorController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }


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
    public function handleError(Throwable $exception): Response
    {
        $code = 500;
        if ($exception instanceof HttpExceptionInterface) {
            $code = $exception->getStatusCode();
        } else {
            $this->logger->error($exception->getMessage());
        }
        return $this->showError($code);
    }
}
