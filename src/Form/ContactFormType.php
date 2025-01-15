<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('last_name', TextType::class, [
                'constraints' => [
                    new Length(['min' => 1, 'minMessage' => 'Veuillez renseigner votre nom']),
                ],
            ])
            ->add('first_name', TextType::class, [
                'constraints' => [
                    new Length(['min' => 1, 'minMessage' => 'Veuillez renseigner votre prénom']),
                ],
            ])
            ->add('email', EmailType::class)
            ->add('subject', ChoiceType::class, [
                'choices' => [
                    'Création de compte pour une entreprise/lycéen/alumni...' => 'création de compte pour une entreprise/lycéen/alumni...',
                    'Bug sur le site' => 'bug sur le site',
                    'Renseignement' => 'renseignement',
                    'Autre' => 'autre'
                ],
                'expanded' => false,
                'multiple' => false
            ])
            ->add('message', TextareaType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
