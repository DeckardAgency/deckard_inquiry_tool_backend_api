<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

class InquiryStatusChangedMessage
{
    private Uuid $inquiryId;
    private string $oldStatus;
    private string $newStatus;

    public function __construct(Uuid $inquiryId, string $oldStatus, string $newStatus)
    {
        $this->inquiryId = $inquiryId;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function getInquiryId(): Uuid
    {
        return $this->inquiryId;
    }

    public function getOldStatus(): string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
