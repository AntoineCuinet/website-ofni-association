<?php

namespace App\Twig;

use App\Service\FormWidgetAnswerFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FormWidgetExtension extends AbstractExtension
{
    public function __construct(private FormWidgetAnswerFormatter $formatter)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('registration_columns', $this->formatter->columns(...)),
            new TwigFunction('registration_answer', $this->formatter->answer(...)),
        ];
    }
}
