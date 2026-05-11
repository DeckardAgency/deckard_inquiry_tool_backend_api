<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

class InquiryCreatedMessage
{
    private Uuid $inquiryId;

    public function __construct(Uuid $inquiryId)
    {
        $this->inquiryId = $inquiryId;
    }

    public function getInquiryId(): Uuid
    {
        return $this->inquiryId;
    }
}
