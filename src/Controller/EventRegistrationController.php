<?php

namespace App\Controller;

use App\Entity\AssoEventInstance;
use App\Entity\Registration;
use App\Entity\RegistrationForm;
use App\Entity\User;
use App\Form\EventRegistrationAnswerType;
use App\Form\EventRegistrationFormType;
use App\Form\FormWidgetFieldResolver;
use App\Form\FormWidgetViewType;
use App\Repository\RegistrationRepository;
use App\Service\FormWidgetAnswerFormatter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EventRegistrationController extends AbstractController
{
    #[Route('/events/instance/{slug}-{id}/inscription', name: 'event.instance.register', requirements: ['slug' => '[a-z0-9\-]+', 'id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function register(AssoEventInstance $instance, Request $request, EntityManagerInterface $em, RegistrationRepository $repository, MailerInterface $mailer): Response
    {
        $registrationForm = $instance->getRegistrationForm() ?? throw $this->createNotFoundException();
        /** @var User $user */
        $user = $this->getUser();

        $alreadyRegistered = null !== $repository->findOneBy(['form' => $registrationForm, 'user' => $user]);
        if ($alreadyRegistered || !$registrationForm->isAcceptingRegistrations()) {
            // page rendering, without form
            return $this->render('event_registration/register.html.twig', [
                'instance' => $instance,
                'registrationForm' => $registrationForm,
                'alreadyRegistered' => $alreadyRegistered,
                'form' => null,
            ]);
        }

        // form create and handling
        $form = $this->createForm(EventRegistrationAnswerType::class, null, [
            'registration_form' => $registrationForm,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $registration = (new Registration())
                ->setForm($registrationForm)
                ->setUser($user)
                ->setAnswers($this->cleanAnswers($form->get('answers')->getData() ?? []));
            $em->persist($registration);
            try {
                $em->flush();
            } catch (UniqueConstraintViolationException) {
                // double submit: the first request already registered this user
                return $this->redirectToShow($instance);
            }

            $this->addFlash('success', $this->sendConfirmation($mailer, $registration)
                ? 'Votre inscription a bien été enregistrée. Un email de confirmation vous a été envoyé.'
                : 'Votre inscription a bien été enregistrée.');

            return $this->redirectToShow($instance);
        }

        // page rendering
        return $this->render('event_registration/register.html.twig', [
            'instance' => $instance,
            'registrationForm' => $registrationForm,
            'alreadyRegistered' => false,
            'form' => $form,
        ]);
    }

    #[Route('/events/instance/{id}/inscription/annuler', name: 'event.instance.register.cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(AssoEventInstance $instance, Request $request, EntityManagerInterface $em, RegistrationRepository $repository): Response
    {
        $registrationForm = $instance->getRegistrationForm() ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('cancel-registration-'.$instance->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');
        } elseif (!$registrationForm->isAcceptingRegistrations()) {
            $this->addFlash('error', 'Les inscriptions sont fermées, votre inscription ne peut plus être annulée.');
        } elseif ($registration = $repository->findOneBy(['form' => $registrationForm, 'user' => $this->getUser()])) {
            $em->remove($registration);
            $em->flush();
            $this->addFlash('success', 'Votre inscription a été annulée.');
        }

        return $this->redirectToShow($instance);
    }

    #[Route('/admin/events/instance/{id}/registration', name: 'admin.event.instance.registration', requirements: ['id' => '\d+'])]
    public function adminForm(AssoEventInstance $instance, Request $request, EntityManagerInterface $em): Response
    {
        // form create and handling
        $registrationForm = $instance->getRegistrationForm() ?? new RegistrationForm();
        // snapshot for the status line: handling an invalid submit still changes the entity in memory
        $savedRegistrationForm = $instance->getRegistrationForm() ? clone $registrationForm : null;
        $form = $this->createForm(EventRegistrationFormType::class, $registrationForm);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (null === $registrationForm->getId()) {
                $registrationForm->setInstance($instance);
                $em->persist($registrationForm);
            }
            $em->flush();
            $this->addFlash('success', 'Le formulaire d\'inscription a été enregistré.');

            return $this->redirectToRoute('admin.event.instance.registration', ['id' => $instance->getId()]);
        }

        // page rendering
        return $this->render('event_registration/admin_form.html.twig', [
            'instance' => $instance,
            'registrationForm' => $instance->getRegistrationForm(),
            'savedRegistrationForm' => $savedRegistrationForm,
            'form' => $form,
            // what users currently see
            'preview' => $savedRegistrationForm
                ? $this->createForm(FormWidgetViewType::class, null, ['widget' => $savedRegistrationForm->getWidget(), 'csrf_protection' => false, 'label' => false])->createView()
                : null,
        ]);
    }

    #[Route('/admin/events/instance/{id}/registration/delete', name: 'admin.event.instance.registration.delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function adminFormDelete(AssoEventInstance $instance, Request $request, EntityManagerInterface $em): Response
    {
        $registrationForm = $instance->getRegistrationForm() ?? throw $this->createNotFoundException();

        if ($this->isCsrfTokenValid('delete-registration-form-'.$registrationForm->getId(), $request->request->get('_token'))) {
            $instance->setRegistrationForm(null);
            $em->remove($registrationForm);
            $em->flush();
            $this->addFlash('success', 'Le formulaire d\'inscription a été supprimé.');
        }

        return $this->redirectToRoute('admin.event');
    }

    #[Route('/admin/events/instance/{id}/registrations', name: 'admin.event.instance.registrations', requirements: ['id' => '\d+'])]
    public function adminRegistrations(AssoEventInstance $instance): Response
    {
        $registrationForm = $instance->getRegistrationForm() ?? throw $this->createNotFoundException();

        // page rendering
        return $this->render('event_registration/admin_registrations.html.twig', [
            'instance' => $instance,
            'registrationForm' => $registrationForm,
        ]);
    }

    #[Route('/admin/events/instance/{id}/registrations.csv', name: 'admin.event.instance.registrations.csv', requirements: ['id' => '\d+'])]
    public function adminRegistrationsCsv(AssoEventInstance $instance, FormWidgetAnswerFormatter $formatter): Response
    {
        $registrationForm = $instance->getRegistrationForm() ?? throw $this->createNotFoundException();
        $columns = $formatter->columns($registrationForm);

        $response = new StreamedResponse(function () use ($registrationForm, $columns, $formatter) {
            $out = fopen('php://output', 'w');
            // BOM + ";" so the file opens correctly in a French Excel
            fwrite($out, "\u{FEFF}");
            $header = ['Nom', 'Prénom', 'Pseudo', 'Email', 'Date'];
            foreach ($columns as $column) {
                $header[] = $column->name;
            }
            fputcsv($out, $header, ';', '"', '');
            foreach ($registrationForm->getRegistrations() as $registration) {
                $user = $registration->getUser();
                $row = [$user->getName(), $user->getFirstName(), $user->getPseudo(), $user->getEmail(), $registration->getCreatedAt()->format('d/m/Y H:i')];
                foreach ($columns as $column) {
                    $row[] = $formatter->answer($registration, $column);
                }
                fputcsv($out, $row, ';', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', 'inscriptions-'.$instance->getSlug().'.csv'));

        return $response;
    }

    #[Route('/admin/registrations/{id}/delete', name: 'admin.registration.delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function adminRegistrationDelete(Registration $registration, Request $request, EntityManagerInterface $em): Response
    {
        $instance = $registration->getForm()->getInstance();

        if ($this->isCsrfTokenValid('delete-registration-'.$registration->getId(), $request->request->get('_token'))) {
            $em->remove($registration);
            $em->flush();
            $this->addFlash('success', 'L\'inscription a été supprimée.');
        }

        return $this->redirectToRoute('admin.event.instance.registrations', ['id' => $instance->getId()]);
    }

    private function sendConfirmation(MailerInterface $mailer, Registration $registration): bool
    {
        $email = (new TemplatedEmail())
            ->from('no-reply@ofni.asso.fr')
            ->to($registration->getUser()->getEmail())
            ->subject('Confirmation d\'inscription : '.$registration->getForm()->getInstance()->getName())
            ->htmlTemplate('event_registration/confirmation_email.html.twig')
            ->context([
                'registration' => $registration,
                'instance' => $registration->getForm()->getInstance(),
            ]);

        try {
            $mailer->send($email);

            return true;
        } catch (TransportExceptionInterface) {
            // the registration is saved anyway, only the confirmation is lost
            return false;
        }
    }

    /**
     * Drops empty values and renumbers list entries (removed entries leave gaps).
     */
    private function cleanAnswers(array $data): array
    {
        $answers = [];
        foreach ($data as $key => $value) {
            if (FormWidgetFieldResolver::isEmptyData($value)) {
                continue;
            }
            $answers[$key] = is_array($value) ? $this->cleanAnswers($value) : $value;
        }

        // list entries have integer keys, widgets "widget_<id>" ones
        $isList = [] !== $answers && array_filter(array_keys($answers), 'is_int') === array_keys($answers);

        return $isList ? array_values($answers) : $answers;
    }

    private function redirectToShow(AssoEventInstance $instance): Response
    {
        return $this->redirectToRoute('event.instance.show', [
            'id' => $instance->getId(),
            'slug' => $instance->getSlug(),
        ]);
    }
}
