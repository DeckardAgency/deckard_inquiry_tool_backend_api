<?php

namespace App\Message;

class InquiryOfferSentMessage
{
    public function __construct(
        private string $offerId
    ) {
    }

    public function getOfferId(): string
    {
        return $this->offerId;
    }
}
