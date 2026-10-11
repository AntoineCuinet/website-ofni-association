<?php

namespace App\Form;

use App\Entity\RegistrationForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Admin builder for the registration form attached to an event instance.
 */
class EventRegistrationFormType extends AbstractType
{
    public const STATUS_CLOSED = 'closed';
    public const STATUS_OPEN = 'open';
    public const STATUS_UNTIL = 'until';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description (affichée au-dessus du formulaire)',
                'required' => false,
            ])
            // "status" drives both isOpen and closesAt, so a deadline can't be set on a closed form
            ->add('status', ChoiceType::class, [
                'label' => 'Statut des inscriptions',
                'mapped' => false,
                'choices' => [
                    'Fermées' => self::STATUS_CLOSED,
                    'Ouvertes' => self::STATUS_OPEN,
                    'Ouvertes jusqu\'au…' => self::STATUS_UNTIL,
                ],
                'attr' => ['data-registration-status' => true],
            ])
            ->add('closesAt', DateTimeType::class, [
                'label' => 'Date de clôture',
                'widget' => 'single_text',
                'required' => false,
                'row_attr' => ['data-registration-closes-at' => true],
            ])
            ->add('fields', CollectionType::class, [
                'label' => 'Champs',
                'entry_type' => EventRegistrationFieldType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn'],
            ])
        ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            /** @var RegistrationForm $registrationForm */
            $registrationForm = $event->getData();
            $event->getForm()->get('status')->setData(match (true) {
                !$registrationForm->isOpen() => self::STATUS_CLOSED,
                null === $registrationForm->getClosesAt() => self::STATUS_OPEN,
                default => self::STATUS_UNTIL,
            });
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            /** @var RegistrationForm $registrationForm */
            $registrationForm = $event->getData();
            $form = $event->getForm();
            $status = $form->get('status')->getData();

            $registrationForm->setOpen(self::STATUS_CLOSED !== $status);
            if (self::STATUS_UNTIL !== $status) {
                // a date left in the (hidden) field means nothing for these statuses
                $registrationForm->setClosesAt(null);
            } elseif (null === $registrationForm->getClosesAt()) {
                $form->get('closesAt')->addError(new FormError('Indiquez la date de clôture des inscriptions.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationForm::class,
        ]);
    }
}
