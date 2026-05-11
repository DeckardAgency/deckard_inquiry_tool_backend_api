<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

#[AsController]
class OrderPdfController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly Environment $twig,
        private readonly Pdf $knpSnappyPdf
    ) {
    }

    public function __invoke(string $id): Response
    {
        // Validate UUID
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException('Invalid order ID format');
        }

        // Find the order
        $order = $this->orderRepository->find($uuid);

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        // Check if user has access to this order
        $this->denyAccessUnlessGranted('VIEW', $order);

        // Render the PDF template
        $html = $this->twig->render('pdf/order.html.twig', [
            'order' => $order,
            'generatedAt' => new \DateTime(),
        ]);

        // Generate filename
        $filename = sprintf('order_%s_%s.pdf',
            $order->getOrderNumber(),
            date('Y-m-d_His')
        );

        // Return PDF response
        return new PdfResponse(
            $this->knpSnappyPdf->getOutputFromHtml($html, [
                'encoding' => 'utf-8',
                'enable-local-file-access' => true,
                'page-size' => 'A4',
                'margin-top' => '10mm',
                'margin-bottom' => '10mm',
                'margin-left' => '10mm',
                'margin-right' => '10mm',
            ]),
            $filename
        );
    }
}
