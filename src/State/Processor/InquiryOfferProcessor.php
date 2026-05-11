<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inquiry;
use App\Entity\InquiryOffer;
use App\Message\InquiryOfferSentMessage;
use App\Message\InquiryOfferRespondedMessage;
use App\Repository\InquiryOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class InquiryOfferProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly InquiryOfferRepository $offerRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InquiryOffer
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('You must be logged in');
        }

        $offerId = $uriVariables['id'] ?? null;

        if (!$offerId) {
            throw new NotFoundHttpException('Offer ID is required');
        }

        $offer = $this->offerRepository->find($offerId);

        if (!$offer) {
            throw new NotFoundHttpException('Offer not found');
        }

        $path = $operation->getUriTemplate();

        if (str_contains($path, '/send')) {
            return $this->handleSend($offer);
        }

        if (str_contains($path, '/respond')) {
            return $this->handleRespond($offer, $data);
        }

        throw new BadRequestHttpException('Unknown operation');
    }

    private function handleSend(InquiryOffer $offer): InquiryOffer
    {
        if ($offer->getStatus() !== InquiryOffer::STATUS_DRAFT) {
            throw new BadRequestHttpException('Only draft offers can be sent');
        }

        if ($offer->getItems()->isEmpty()) {
            throw new BadRequestHttpException('Offer must have at least one item');
        }

        if ($offer->getPdfDocument() === null) {
            throw new BadRequestHttpException('Offer must have a PDF document attached');
        }

        // Recalculate total
        $offer->recalculateTotalAmount();
        $offer->setStatus(InquiryOffer::STATUS_SENT);

        // Auto-flip parent inquiry to "completed" when sending an offer from "in_progress".
        // Same flush as the offer status change → one transaction; if flush throws, neither persists.
        $inquiry = $offer->getInquiry();
        $inquiryStatusChanged = false;
        if ($inquiry->getStatus() === Inquiry::STATUS_IN_PROGRESS) {
            $inquiry->setStatus(Inquiry::STATUS_COMPLETED);
            $inquiryStatusChanged = true;
        }

        $this->entityManager->flush();

        $this->logger->info('Offer sent to client', [
            'offer_id' => $offer->getId()->toRfc4122(),
            'offer_number' => $offer->getOfferNumber(),
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'inquiry_status_flipped_to_completed' => $inquiryStatusChanged
        ]);

        // Dispatch notification message
        $this->messageBus->dispatch(new InquiryOfferSentMessage(
            $offer->getId()->toRfc4122()
        ));

        return $offer;
    }

    private function handleRespond(InquiryOffer $offer, mixed $data): InquiryOffer
    {
        // API Platform deserializes the request body into the entity before calling the processor,
        // so $offer->getStatus() already reflects the new status from the request.
        // Use UnitOfWork to check the original status from the database.
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($offer);
        $originalStatus = $originalData['status'] ?? $offer->getStatus();

        if ($originalStatus !== InquiryOffer::STATUS_SENT) {
            throw new BadRequestHttpException('Only sent offers can be responded to');
        }

        // $data comes from the request body deserialization
        $status = null;
        $rejectionReason = null;

        if ($data instanceof InquiryOffer) {
            $status = $data->getStatus();
            $rejectionReason = $data->getRejectionReason();
        }

        if (!$status || !in_array($status, [InquiryOffer::STATUS_ACCEPTED, InquiryOffer::STATUS_REJECTED])) {
            throw new BadRequestHttpException('Status must be "accepted" or "rejected"');
        }

        if ($status === InquiryOffer::STATUS_REJECTED && empty($rejectionReason)) {
            throw new BadRequestHttpException('A rejection reason is required when rejecting an offer');
        }

        $offer->setStatus($status);
        $offer->setRespondedAt(new \DateTime());

        if ($status === InquiryOffer::STATUS_REJECTED) {
            $offer->setRejectionReason($rejectionReason);
        }

        // Auto-flip parent inquiry to "accepted" when client accepts the offer.
        // Same flush as the offer status change → one transaction; if flush throws, neither persists.
        $inquiry = $offer->getInquiry();
        $inquiryStatusChanged = false;
        if ($status === InquiryOffer::STATUS_ACCEPTED
            && $inquiry->getStatus() === Inquiry::STATUS_COMPLETED) {
            $inquiry->setStatus(Inquiry::STATUS_ACCEPTED);
            $inquiryStatusChanged = true;
        }

        $this->entityManager->flush();

        $this->logger->info('Offer ' . $status . ' by client', [
            'offer_id' => $offer->getId()->toRfc4122(),
            'offer_number' => $offer->getOfferNumber(),
            'status' => $status,
            'inquiry_status_flipped_to_accepted' => $inquiryStatusChanged
        ]);

        // Dispatch notification message
        $this->messageBus->dispatch(new InquiryOfferRespondedMessage(
            $offer->getId()->toRfc4122(),
            $status
        ));

        return $offer;
    }
}
