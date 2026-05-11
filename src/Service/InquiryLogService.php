<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\InquiryLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class InquiryLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    /**
     * Log inquiry status change (excluding transitions to draft)
     */
    public function logStatusChange(
        Inquiry $inquiry,
        string $previousStatus,
        string $newStatus,
        ?string $comment = null,
        ?array $metadata = null
    ): ?InquiryLog {
        // Skip logging if new status is draft (but allow transitions FROM draft)
        if ($newStatus === Inquiry::STATUS_DRAFT) {
            return null;
        }

        // Skip if status hasn't actually changed
        if ($previousStatus === $newStatus) {
            return null;
        }

        $log = new InquiryLog();
        $log->setInquiry($inquiry);
        $log->setPreviousStatus($previousStatus);
        $log->setNewStatus($newStatus);
        $log->setComment($comment);

        // Set the user who made the change
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $log->setChangedBy($currentUser);
        }

        // Add metadata if provided
        if ($metadata !== null) {
            $log->setMetadata($metadata);
        }

        // Add inquiry details to metadata
        $log->addMetadata('inquiry_number', $inquiry->getInquiryNumber());
        $log->addMetadata('machines_count', $inquiry->getMachines()->count());

        $this->entityManager->persist($log);
        $inquiry->addLog($log);

        return $log;
    }

    /**
     * Log inquiry submission (transition from draft to submitted)
     */
    public function logInquirySubmission(Inquiry $inquiry, ?string $comment = null, ?array $metadata = null): ?InquiryLog
    {
        // Special case: log when transitioning FROM draft to submitted
        if ($inquiry->getStatus() === Inquiry::STATUS_SUBMITTED) {
            $log = new InquiryLog();
            $log->setInquiry($inquiry);
            $log->setPreviousStatus(Inquiry::STATUS_DRAFT);
            $log->setNewStatus(Inquiry::STATUS_SUBMITTED);
            $log->setComment($comment ?? 'Inquiry submitted from draft');

            $currentUser = $this->security->getUser();
            if ($currentUser instanceof User) {
                $log->setChangedBy($currentUser);
            }

            $log->addMetadata('inquiry_number', $inquiry->getInquiryNumber());
            $log->addMetadata('machines_count', $inquiry->getMachines()->count());
            $log->addMetadata('submission_type', 'draft_to_submitted');

            // Add any additional metadata
            if ($metadata !== null) {
                foreach ($metadata as $key => $value) {
                    $log->addMetadata($key, $value);
                }
            }

            $this->entityManager->persist($log);
            $inquiry->addLog($log);

            return $log;
        }

        return null;
    }

    /**
     * Get status change history for an inquiry (excluding draft)
     *
     * @return InquiryLog[]
     */
    public function getInquiryHistory(Inquiry $inquiry): array
    {
        return $this->entityManager->getRepository(InquiryLog::class)
            ->findByInquiry($inquiry);
    }

    /**
     * Get the last status change for an inquiry
     */
    public function getLastStatusChange(Inquiry $inquiry): ?InquiryLog
    {
        $history = $this->getInquiryHistory($inquiry);
        return !empty($history) ? $history[0] : null;
    }

    /**
     * Log bulk status change for multiple inquiries
     *
     * @param Inquiry[] $inquiries
     */
    public function logBulkStatusChange(
        array $inquiries,
        string $newStatus,
        ?string $comment = null
    ): array {
        $logs = [];

        foreach ($inquiries as $inquiry) {
            $previousStatus = $inquiry->getStatus();
            $inquiry->setStatus($newStatus);

            $log = $this->logStatusChange(
                $inquiry,
                $previousStatus,
                $newStatus,
                $comment,
                ['bulk_update' => true]
            );

            if ($log !== null) {
                $logs[] = $log;
            }
        }

        return $logs;
    }
}
