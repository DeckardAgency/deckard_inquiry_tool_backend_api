<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class ProductExcelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    #[Route('/api/products/export/excel', name: 'api_products_export_excel', methods: ['GET'])]
    public function exportToExcel(ProductRepository $productRepository): Response
    {
        try {
            ini_set('memory_limit', '256M');
            set_time_limit(300); // 5 minutes

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Products');

            // Set headers with styling
            $headers = [
                'ID',
                'Name',
                'Part Number',
                'Short Description',
                'Unit',
                'Price',
                'Weight',
                'Technical Description',
                'Featured Image',
                'Created At',
                'Updated At'
            ];

            // Style the header row
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ];

            // Write headers
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column . '1', $header);
                $sheet->getStyle($column . '1')->applyFromArray($headerStyle);
                $sheet->getColumnDimension($column)->setAutoSize(true);
                $column++;
            }

            // Fetch data in batches to avoid memory issues
            $query = $productRepository->createQueryBuilder('p')
                ->leftJoin('p.featuredImage', 'fi')
                ->getQuery();

            $query->setHint('doctrine.orm.disable_many_to_one_fetch', true);

            $row = 2;
            foreach ($query->toIterable() as $product) {
                $sheet->setCellValue('A' . $row, $product->getId());
                $sheet->setCellValue('B' . $row, $product->getName());
                $sheet->setCellValue('C' . $row, $product->getPartNo());
                $sheet->setCellValue('D' . $row, $product->getShortDescription());
                $sheet->setCellValue('E' . $row, $product->getUnit());
                $sheet->setCellValue('F' . $row, $product->getPrice());
                $sheet->setCellValue('G' . $row, $product->getWeight());
                $sheet->setCellValue('H' . $row, $product->getTechnicalDescription());
                $sheet->setCellValue('I' . $row, $product->getFeaturedImage() ? $product->getFeaturedImage()->getFilename() : '');
                $sheet->setCellValue('J' . $row, $product->getCreatedAt() ? $product->getCreatedAt()->format('Y-m-d H:i:s') : '');
                $sheet->setCellValue('K' . $row, $product->getUpdatedAt() ? $product->getUpdatedAt()->format('Y-m-d H:i:s') : '');

                // Apply zebra striping
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2']
                        ]
                    ]);
                }

                $row++;

                // Clear entity manager every 100 products to free memory
                if ($row % 100 == 0) {
                    $this->entityManager->clear();
                }
            }

            // Add autofilter
            $sheet->setAutoFilter('A1:K' . ($row - 1));

            // Create the writer
            $writer = new Xlsx($spreadsheet);

            // Create a streamed response
            $response = new StreamedResponse();
            $response->setCallback(function () use ($writer) {
                $writer->save('php://output');
            });

            // Set response headers
            $filename = 'products_' . date('Y-m-d_H-i-s') . '.xlsx';
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error exporting products to Excel: ' . $e->getMessage());
            return new Response('Error generating Excel file', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
