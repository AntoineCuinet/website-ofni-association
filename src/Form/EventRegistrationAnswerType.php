<?php

namespace App\Form;

use App\Entity\RegistrationForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The registration form a user fills in, generated from the form's widget.
 * Answers are the data of the "answers" child.
 */
class EventRegistrationAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var RegistrationForm $form */
        $form = $options['registration_form'];

        $builder
            ->add('answers', FormWidgetViewType::class, [
                'widget' => $form->getWidget(),
                'label' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'S\'inscrire',
                'attr' => ['class' => 'btn-full'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('registration_form');
        $resolver->setAllowedTypes('registration_form', RegistrationForm::class);
    }
}
