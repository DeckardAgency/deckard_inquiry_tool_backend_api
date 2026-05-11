<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CompleteInvitationInput
{
    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Password must be at least {{ limit }} characters long'
    )]
    #[Assert\Regex(
        pattern: '/[A-Z]/',
        message: 'Password must contain at least one uppercase letter'
    )]
    #[Assert\Regex(
        pattern: '/[a-z]/',
        message: 'Password must contain at least one lowercase letter'
    )]
    #[Assert\Regex(
        pattern: '/[0-9]/',
        message: 'Password must contain at least one number'
    )]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'Password confirmation is required')]
    #[Assert\EqualTo(
        propertyPath: 'password',
        message: 'Passwords do not match'
    )]
    public ?string $passwordConfirm = null;
}
