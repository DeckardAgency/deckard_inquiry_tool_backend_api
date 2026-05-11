<?php

namespace App\Message;

class InquiryOfferRespondedMessage
{
    public function __construct(
        private string $offerId,
        private string $responseStatus
    ) {
    }

    public function getOfferId(): string
    {
        return $this->offerId;
    }

    public function getResponseStatus(): string
    {
        return $this->responseStatus;
    }
}
