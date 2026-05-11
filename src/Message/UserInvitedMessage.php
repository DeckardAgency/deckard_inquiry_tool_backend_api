<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

class UserInvitedMessage
{
    private Uuid $invitationId;

    public function __construct(Uuid $invitationId)
    {
        $this->invitationId = $invitationId;
    }

    public function getInvitationId(): Uuid
    {
        return $this->invitationId;
    }
}
