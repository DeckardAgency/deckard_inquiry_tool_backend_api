<?php

namespace App\Message;

/**
 * Message dispatched when info request actions occur
 */
class InquiryPartInfoRequestMessage
{
    public function __construct(
        private string $infoRequestId,
        private string $action,
        private ?string $newStatus = null
    ) {
    }

    public function getInfoRequestId(): string
    {
        return $this->infoRequestId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }
}
