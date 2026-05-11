<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class ClientExcelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    #[Route('/api/clients/export/excel', name: 'clients_export_excel', methods: ['GET'])]
    public function exportToExcel(
        Request $request,
        ClientRepository $clientRepository
    ): StreamedResponse {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300); // 5 minutes

            $spreadsheet = new Spreadsheet();

            // Create Clients sheet
            $clientsSheet = $spreadsheet->getActiveSheet();
            $clientsSheet->setTitle('Clients');

            // Headers for clients
            $clientHeaders = [
                'ID',
                'Name',
                'Code',
                'Email',
                'Phone',
                'Address',
                'VAT Number',
                'Description',
                'Active',
                'Archived',
                'Max Active Users',
                'Machines Count',
                'Users Count',
                'Created At',
                'Updated At'
            ];

            $this->writeHeaders($clientsSheet, $clientHeaders);

            // Build query based on filters
            $qb = $clientRepository->createQueryBuilder('c')
                ->leftJoin('c.users', 'u')
                ->select('c', 'u')
                ->orderBy('c.name', 'ASC');

            // Apply isArchived filter if provided
            $isArchived = $request->query->get('isArchived');
            if ($isArchived === 'true') {
                $qb->andWhere('c.isArchived = :isArchived')
                   ->setParameter('isArchived', true);
            } elseif ($isArchived === 'false') {
                $qb->andWhere('c.isArchived = :isArchived')
                   ->setParameter('isArchived', false);
            }

            // Apply name filter if provided
            $name = $request->query->get('name');
            if ($name) {
                $qb->andWhere('c.name LIKE :name')
                   ->setParameter('name', '%' . $name . '%');
            }

            // Apply code filter if provided
            $code = $request->query->get('code');
            if ($code) {
                $qb->andWhere('c.code = :code')
                   ->setParameter('code', $code);
            }

            $query = $qb->getQuery();

            $row = 2;
            foreach ($query->getResult() as $client) {
                $clientsSheet->setCellValue('A' . $row, $client->getId());
                $clientsSheet->setCellValue('B' . $row, $client->getName());
                $clientsSheet->setCellValue('C' . $row, $client->getCode());
                $clientsSheet->setCellValue('D' . $row, $client->getEmail() ?? '');
                $clientsSheet->setCellValue('E' . $row, $client->getPhoneNumber() ?? '');
                $clientsSheet->setCellValue('F' . $row, $client->getAddress() ?? '');
                $clientsSheet->setCellValue('G' . $row, $client->getVatNumber() ?? '');
                $clientsSheet->setCellValue('H' . $row, $client->getDescription() ?? '');
                $clientsSheet->setCellValue('I' . $row, $client->getIsActive() ? 'Yes' : 'No');
                $clientsSheet->setCellValue('J' . $row, $client->getIsArchived() ? 'Yes' : 'No');
                $clientsSheet->setCellValue('K' . $row, $client->getMaxActiveUsers() ?? 'Unlimited');
                $clientsSheet->setCellValue('L' . $row, $client->getMachinesCount());
                $clientsSheet->setCellValue('M' . $row, $client->getUsers()->count());
                $clientsSheet->setCellValue('N' . $row, $client->getCreatedAt() ? $client->getCreatedAt()->format('Y-m-d H:i:s') : '');
                $clientsSheet->setCellValue('O' . $row, $client->getUpdatedAt() ? $client->getUpdatedAt()->format('Y-m-d H:i:s') : '');

                // Apply alternating row colors
                if ($row % 2 == 0) {
                    $clientsSheet->getStyle('A' . $row . ':O' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2']
                        ]
                    ]);
                }

                // Apply borders
                $clientsSheet->getStyle('A' . $row . ':O' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9D9D9']
                        ]
                    ]
                ]);

                $row++;
            }

            // Add autofilter
            $clientsSheet->setAutoFilter('A1:O' . ($row - 1));

            // Create Summary sheet
            $summarySheet = $spreadsheet->createSheet();
            $summarySheet->setTitle('Summary');
            $this->createSummarySheet($summarySheet, $clientRepository);

            // Create the writer
            $writer = new Xlsx($spreadsheet);

            // Create a streamed response
            $response = new StreamedResponse();
            $response->setCallback(function () use ($writer) {
                $writer->save('php://output');
            });

            // Set response headers
            $filename = 'clients_' . date('Y-m-d_H-i-s') . '.xlsx';
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error exporting clients to Excel: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createSummarySheet($sheet, ClientRepository $clientRepository): void
    {
        $sheet->setCellValue('A1', 'Client Export Summary');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A3', 'Export Date:');
        $sheet->setCellValue('B3', date('Y-m-d H:i:s'));

        // Calculate statistics
        $totalClients = $clientRepository->count([]);
        $activeClients = $clientRepository->count(['isActive' => true]);
        $archivedClients = $clientRepository->count(['isArchived' => true]);

        $sheet->setCellValue('A5', 'Total Clients:');
        $sheet->setCellValue('B5', $totalClients);

        $sheet->setCellValue('A6', 'Active Clients:');
        $sheet->setCellValue('B6', $activeClients);

        $sheet->setCellValue('A7', 'Archived Clients:');
        $sheet->setCellValue('B7', $archivedClients);

        // Format the summary sheet
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);
    }

    private function writeHeaders($sheet, array $headers): void
    {
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
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $sheet->getStyle($column . '1')->applyFromArray($headerStyle);
            $sheet->getColumnDimension($column)->setAutoSize(true);
            $column++;
        }
    }
}
