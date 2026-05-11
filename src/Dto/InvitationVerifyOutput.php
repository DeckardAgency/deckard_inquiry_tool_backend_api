<?php

namespace App\Dto;

class InvitationVerifyOutput
{
    public string $email;
    public string $firstName;
    public string $lastName;
    public \DateTimeInterface $expiresAt;
    public bool $isExpired;

    public function __construct(
        string $email,
        string $firstName,
        string $lastName,
        \DateTimeInterface $expiresAt,
        bool $isExpired
    ) {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->expiresAt = $expiresAt;
        $this->isExpired = $isExpired;
    }
}
