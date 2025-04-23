<?php

namespace App\Form;

use App\Entity\FormWidget;
use App\Enum\FormWidgetKind;
use App\Repository\FormWidgetRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class FormWidgetViewType extends AbstractType
{
    private FormWidgetRepository $repository;

    public function __construct(FormWidgetRepository $repository)
    {
        $this->repository = $repository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->recursiveGeneration($builder, $options['widget']);
        $builder->add('submit', SubmitType::class, [
            'label' => 'Valider'
        ]);
    }

    public function recursiveGeneration(FormBuilderInterface $builder, FormWidget $formWidget): void
    {
        switch ($formWidget->getKind()) {
            case FormWidgetKind::NATIVE:
                $this->nativeGeneration($builder, $formWidget->getContent());
                break;
            case FormWidgetKind::COMPOSED:
                $this->composedGeneration($builder, $formWidget->getContent());
                break;
        }
    }

    public function nativeGeneration(FormBuilderInterface $builder, array $content): void
    {
        $class = match ($content['type']) {
            'text' => TextType::class,
            'email' => EmailType::class,
            'password' => PasswordType::class,
            default => TextType::class,
        };
        $builder->add($content['name'], $class);
    }

    public function composedGeneration(FormBuilderInterface $builder, array $content): void
    {
        foreach ($content as $widgetDescription) {
            $widget = $this->repository->find($widgetDescription['widget']);
            $this->recursiveGeneration($builder, $widget);
        }
    }
}
