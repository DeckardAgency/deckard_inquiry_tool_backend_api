<?php

namespace App\Controller;

use App\Repository\InquiryOfferRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class InquiryOfferPdfDownloadController extends AbstractController
{
    public function __construct(
        private readonly InquiryOfferRepository $offerRepository,
        private readonly LoggerInterface $logger,
        private readonly string $uploadDirectory
    ) {
    }

    #[Route('/api/v1/inquiry_offers/{id}/download-pdf', name: 'inquiry_offer_download_pdf', methods: ['GET'])]
    public function __invoke(string $id): BinaryFileResponse
    {
        $offer = $this->offerRepository->find(Uuid::fromString($id));

        if (!$offer) {
            throw new NotFoundHttpException('Offer not found');
        }

        $pdfDocument = $offer->getPdfDocument();

        if (!$pdfDocument) {
            throw new NotFoundHttpException('No PDF document attached to this offer');
        }

        $filePath = $this->uploadDirectory . '/' . basename($pdfDocument->getFilePath());

        if (!file_exists($filePath)) {
            // Try with the full relative path
            $filePath = $this->uploadDirectory . $pdfDocument->getFilePath();

            if (!file_exists($filePath)) {
                $this->logger->error('PDF file not found on disk', [
                    'offer_id' => $id,
                    'file_path' => $pdfDocument->getFilePath()
                ]);
                throw new NotFoundHttpException('PDF file not found');
            }
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $pdfDocument->getFilename()
        );

        return $response;
    }
}
