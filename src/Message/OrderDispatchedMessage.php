<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

class OrderDispatchedMessage
{
    private Uuid $orderId;
    private ?Uuid $dispatchedById;
    private array $documentIds;
    private \DateTimeInterface $dispatchedAt;

    public function __construct(Uuid $orderId, ?Uuid $dispatchedById = null, array $documentIds = [])
    {
        $this->orderId = $orderId;
        $this->dispatchedById = $dispatchedById;
        $this->documentIds = $documentIds;
        $this->dispatchedAt = new \DateTime();
    }

    public function getOrderId(): Uuid
    {
        return $this->orderId;
    }

    public function getDispatchedById(): ?Uuid
    {
        return $this->dispatchedById;
    }

    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }

    public function getDispatchedAt(): \DateTimeInterface
    {
        return $this->dispatchedAt;
    }
}
