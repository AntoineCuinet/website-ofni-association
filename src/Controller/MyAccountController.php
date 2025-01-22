<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MyAccountController extends AbstractController
{

    private FormInterface $namesForm, $pseudoForm, $passwordForm, $deleteAccountForm;


    private function buildEditForms(): void {
        $this->namesForm = $this->container->get('form.factory')->createNamedBuilder('namesForm')
            ->add('name', TextType::class, [
                'data' => $this->getUser()->getName(),
                'constraints' => [
                    new Length(['min' => 2, 'minMessage' => 'Votre nom doit contenir au moins 2 caractères']),
                ]
            ])
            ->add('first_name', TextType::class, [
                'data' => $this->getUser()->getFirstName(),
                'constraints' => [
                    new Length(['min' => 2, 'minMessage' => 'Votre prénom doit contenir au moins 2 caractères']),
                ]
            ])
            ->getForm();
        $this->pseudoForm = $this->container->get('form.factory')->createNamedBuilder('pseudoForm')
            ->add('pseudo', TextType::class, [
                'data' => $this->getUser()->getPseudo(),
                'constraints' => [
                    new Length(['min' => 6, 'minMessage' => 'Le pseudo doit contenir au moins 6 caractères']),
                ]
            ])
            ->getForm();
        $this->passwordForm = $this->container->get('form.factory')->createNamedBuilder('passwordForm')
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passes doivent être identiques',
                'mapped' => false,
                'options' => [
                    'attr' => ['autocomplete' => 'new-password'],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Veuillez entrer un mot de passe.',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                            // max length allowed by Symfony for security reasons
                            'max' => 4096,
                        ]),
                    ]
                ],
                'required' => false,
            ])
            ->getForm();
        $this->deleteAccountForm = $this->container->get('form.factory')->createNamedBuilder('deleteAccountForm')
            ->add('delete', SubmitType::class, [
                'label' => 'Supprimer mon compte',
                'attr' => [
                    'class' => 'btn btn-danger',
                    'id' => 'deleteAccountBtn',
                    'onSubmit' => 'return confirm(\'Voulez vous vraiment supprimer votre compte ? \n Vous ne pourrez pas retourner en arrière.\');'
                ],
            ])
            ->getForm();
    }


    #[Route('/account', name: 'app_account')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session
    ): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->buildEditForms();
        $this->namesForm->handleRequest($request);
        $this->passwordForm->handleRequest($request);
        $this->pseudoForm->handleRequest($request);
        $this->deleteAccountForm->handleRequest($request);


        $changedPseudo = false;
        if ($this->pseudoForm->isSubmitted() && $this->pseudoForm->isValid()) {

            $new_pseudo = $this->pseudoForm->get('pseudo')->getData();
            $old_pseudo = $user->getPseudo();
            $changedPseudo = $old_pseudo != $new_pseudo;

            if ($changedPseudo)
                $user->setPseudo($new_pseudo);

            $entityManager->persist($user);
            $entityManager->flush();
        }

        $changedPassword = false;
        if ($this->passwordForm->isSubmitted() && $this->passwordForm->isValid()) {
            $plainPassword = $this->passwordForm->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $changedPassword = $hashedPassword != $user->getPassword();
                if ($changedPassword)
                    $user->setPassword($hashedPassword);
            }
            $entityManager->persist($user);
            $entityManager->flush();
        }
        $changedNames = false;
        if ($this->namesForm->isSubmitted() && $this->namesForm->isValid()) {

            $new_name = $this->namesForm->get('name')->getData();
            $new_first_name = $this->namesForm->get('first_name')->getData();
            $old_name = $user->getName();
            $old_first_name = $user->getFirstName();
            $changedNames = $old_name != $new_name || $old_first_name != $new_first_name;

            if ($changedNames) {
                $user->setName($new_name);
                $user->setFirstName($new_first_name);
            }

            $entityManager->persist($user);
            $entityManager->flush();
        }


        if ($this->deleteAccountForm->isSubmitted() && $this->deleteAccountForm->isValid()) {
            // log out
            $tokenStorage->setToken(null);
            $session->invalidate();

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a bien été supprimmer. Au revoir.');

            return $this->redirectToRoute('home');
        }

        if ($changedPseudo)
            $this->addFlash('success', 'Votre pseudo a bien été changé');
        if ($changedPassword)
            $this->addFlash('success', 'Votre mot de passe a bien été changé');

        return $this->render('account/my_account.html.twig', [
            'namesForm' => $this->namesForm,
            'pseudoForm' => $this->pseudoForm,
            'passwordForm' => $this->passwordForm,
            'deleteAccountForm' => $this->deleteAccountForm
    ]);

    }


}
