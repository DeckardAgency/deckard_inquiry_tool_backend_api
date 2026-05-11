<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute]
class ActiveUserLimit extends Constraint
{
    public string $message = 'The company "{{ company }}" has reached its maximum limit of {{ limit }} active users. Cannot set this user to active.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
