<?php

namespace App\Dto;

class InvitationCompletedOutput
{
    public string $message;
    public bool $success;

    public function __construct(string $message = 'Account created successfully. You can now login.', bool $success = true)
    {
        $this->message = $message;
        $this->success = $success;
    }
}
