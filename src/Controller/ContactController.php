<?php

namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, MailerInterface $mailer): Response
    {

        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = (new TemplatedEmail())
                ->from('no-reply@ofni.asso.fr')
                ->to('contact@ofni.asso.fr')
                ->subject('Demande de contact : ' . $data["subject"])
                ->htmlTemplate('contact/email.html.twig')
                ->context([
                    'last_name' => $data["last_name"],
                    'first_name' => $data["first_name"],
                    'sender_email' => $data["email"],
                    'message' => $data["message"]
                ]);
            $mailer->send($email);

            $this->addFlash("success", "Votre message a bien été envoyé");
        } else {
            $this->addFlash("error", "Le message ne s'est pas envoyé.");
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}
