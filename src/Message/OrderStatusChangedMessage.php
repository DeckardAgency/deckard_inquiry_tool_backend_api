<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

class OrderStatusChangedMessage
{
    private Uuid $orderId;
    private string $oldStatus;
    private string $newStatus;
    private ?array $modifiedBy = null;
    private ?\DateTimeInterface $changedAt = null;

    public function __construct(Uuid $orderId, string $oldStatus, string $newStatus)
    {
        $this->orderId = $orderId;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->changedAt = new \DateTime();
    }

    public function getOrderId(): Uuid
    {
        return $this->orderId;
    }

    public function getOldStatus(): string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }

    public function getModifiedBy(): ?array
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?array $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;
        return $this;
    }

    public function getChangedAt(): ?\DateTimeInterface
    {
        return $this->changedAt;
    }

    public function setChangedAt(?\DateTimeInterface $changedAt): self
    {
        $this->changedAt = $changedAt;
        return $this;
    }

    public function getModifiedByFullName(): ?string
    {
        if (!$this->modifiedBy) {
            return null;
        }

        return $this->modifiedBy['fullName'] ??
            ($this->modifiedBy['firstName'] . ' ' . $this->modifiedBy['lastName']) ??
            $this->modifiedBy['email'] ??
            'Unknown User';
    }
}
