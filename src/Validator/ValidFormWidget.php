<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that a FormWidget's content matches its kind and doesn't contain itself.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidFormWidget extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
