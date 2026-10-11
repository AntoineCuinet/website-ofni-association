<?php

namespace App\Form;

use App\Entity\RegistrationField;
use App\Entity\RegistrationForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The registration form a user fills in, built from the admin-defined fields.
 * Each field becomes a child named "field_<id>".
 */
class EventRegistrationAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var RegistrationForm $form */
        $form = $options['registration_form'];

        foreach ($form->getFields() as $field) {
            $constraints = $field->isRequired() ? [new NotBlank(message: 'Ce champ est obligatoire.')] : [];
            $fieldOptions = [
                'label' => $field->getLabel(),
                'required' => $field->isRequired(),
            ];

            switch ($field->getType()) {
                case RegistrationField::TYPE_TEXTAREA:
                    $type = TextareaType::class;
                    $constraints[] = new Length(max: 5000);
                    break;
                case RegistrationField::TYPE_CHOICE:
                    $type = ChoiceType::class;
                    $choices = $field->getChoices();
                    $fieldOptions['choices'] = array_combine($choices, $choices);
                    $fieldOptions['placeholder'] = 'Choisir…';
                    break;
                default:
                    $type = TextType::class;
                    $constraints[] = new Length(max: 255);
            }

            $builder->add('field_'.$field->getId(), $type, $fieldOptions + ['constraints' => $constraints]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'S\'inscrire',
            'attr' => ['class' => 'btn-full'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('registration_form');
        $resolver->setAllowedTypes('registration_form', RegistrationForm::class);
    }
}
