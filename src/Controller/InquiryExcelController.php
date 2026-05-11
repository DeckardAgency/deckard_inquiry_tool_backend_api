<?php

namespace App\Controller;

use App\Repository\InquiryRepository;
use App\Repository\InquiryMachinePartRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class InquiryExcelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    #[Route('/api/inquiries/export/excel', name: 'inquiries_export_excel', methods: ['GET'])]
    public function exportToExcel(
        InquiryRepository $inquiryRepository,
        InquiryMachinePartRepository $inquiryMachinePartRepository
    ): StreamedResponse {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300); // 5 minutes

            $spreadsheet = new Spreadsheet();

            // Create Inquiries sheet with machines and parts included
            $inquiriesSheet = $spreadsheet->getActiveSheet();
            $inquiriesSheet->setTitle('Inquiries');

            // Headers for inquiries
            $inquiryHeaders = [
                'Inquiry ID',
                'Inquiry Number',
                'User Email',
                'Status',
                'Contact Email',
                'Contact Phone',
                'Created At',
                'Machine',
                'Custom Machine ID',
                'Machine Notes',
                'Part Name',
                'Part Number',
                'Part Description',
                'Part Notes'
            ];

            $this->writeHeaders($inquiriesSheet, $inquiryHeaders);

            // Fetch inquiries with user data, machines, and parts (excluding draft inquiries)
            $query = $inquiryRepository->createQueryBuilder('i')
                ->leftJoin('i.user', 'u')
                ->leftJoin('i.machines', 'm')
                ->leftJoin('m.machine', 'mach')
                ->leftJoin('m.products', 'p')
                ->select('i', 'u', 'm', 'mach', 'p')
                ->where('i.status != :draftStatus')
                ->setParameter('draftStatus', 'draft')
                ->orderBy('i.inquiryNumber', 'ASC')
                ->addOrderBy('m.id', 'ASC')
                ->addOrderBy('p.id', 'ASC')
                ->getQuery();

            $row = 2;
            $lastInquiryId = null;
            $lastMachineId = null;
            $inquiryStartRow = 2;
            $machineStartRow = 2;

            foreach ($query->getResult() as $inquiry) {
                // Check if this is a new inquiry
                if ($lastInquiryId !== $inquiry->getId()) {
                    // Apply merge styling to previous inquiry if exists
                    if ($lastInquiryId !== null && $row > $inquiryStartRow) {
                        $this->mergeInquiryCells($inquiriesSheet, $inquiryStartRow, $row - 1);
                    }

                    $inquiryStartRow = $row;
                    $lastInquiryId = $inquiry->getId();
                    $lastMachineId = null;
                }

                $user = $inquiry->getUser();
                $machines = $inquiry->getMachines();

                // If inquiry has no machines, write one row with empty machine/part data
                if ($machines->isEmpty()) {
                    $this->writeInquiryRow($inquiriesSheet, $inquiry, $user, null, null, $row);
                    $row++;
                } else {
                    // Write rows for each machine and its parts
                    foreach ($machines as $machine) {
                        // Check if this is a new machine
                        if ($lastMachineId !== $machine->getId()) {
                            $machineStartRow = $row;
                            $lastMachineId = $machine->getId();
                        }

                        $parts = $machine->getProducts();

                        // If machine has no parts, write one row with empty part data
                        if ($parts->isEmpty()) {
                            $this->writeInquiryRow($inquiriesSheet, $inquiry, $user, $machine, null, $row);
                            $row++;
                        } else {
                            // Write a row for each part
                            foreach ($parts as $part) {
                                $this->writeInquiryRow($inquiriesSheet, $inquiry, $user, $machine, $part, $row);
                                $row++;
                            }
                        }
                    }
                }
            }

            // Apply merge styling to the last inquiry
            if ($lastInquiryId !== null && $row > $inquiryStartRow) {
                $this->mergeInquiryCells($inquiriesSheet, $inquiryStartRow, $row - 1);
            }

            // Add autofilter
            $inquiriesSheet->setAutoFilter('A1:N' . ($row - 1));

            // Create separate Inquiry Parts Detail sheet
            $partsSheet = $spreadsheet->createSheet();
            $partsSheet->setTitle('Inquiry Parts Detail');

            $partHeaders = [
                'Inquiry Number',
                'User Email',
                'Machine',
                'Custom Machine ID',
                'Part Name',
                'Part Number',
                'Short Description',
                'Additional Notes',
                'Part Created At'
            ];

            $this->writeHeaders($partsSheet, $partHeaders);

            // Fetch all inquiry machine parts with complete data (excluding draft inquiries)
            $partsQuery = $inquiryMachinePartRepository->createQueryBuilder('imp')
                ->join('imp.inquiryMachine', 'im')
                ->join('im.inquiry', 'i')
                ->leftJoin('im.machine', 'm')
                ->leftJoin('i.user', 'u')
                ->select('imp', 'im', 'i', 'm', 'u')
                ->where('i.status != :draftStatus')
                ->setParameter('draftStatus', 'draft')
                ->orderBy('i.inquiryNumber', 'ASC')
                ->getQuery();

            $row = 2;
            foreach ($partsQuery->getResult() as $part) {
                $inquiryMachine = $part->getInquiryMachine();
                $inquiry = $inquiryMachine->getInquiry();
                $machine = $inquiryMachine->getMachine();
                $user = $inquiry->getUser();

                $partsSheet->setCellValue('A' . $row, $inquiry->getInquiryNumber());
                $partsSheet->setCellValue('B' . $row, $user ? $user->getEmail() : '');
                $partsSheet->setCellValue('C' . $row, $machine ? $machine->getArticleDescription() : '');
                $partsSheet->setCellValue('D' . $row, $inquiryMachine->getCustomMachineId() ?? '');
                $partsSheet->setCellValue('E' . $row, $part->getPartName());
                $partsSheet->setCellValue('F' . $row, $part->getPartNumber() ?? '');
                $partsSheet->setCellValue('G' . $row, $part->getShortDescription() ?? '');
                $partsSheet->setCellValue('H' . $row, $part->getAdditionalNotes() ?? '');
                $partsSheet->setCellValue('I' . $row, $part->getCreatedAt() ? $part->getCreatedAt()->format('Y-m-d H:i:s') : '');

                // Apply styling
                if ($row % 2 == 0) {
                    $partsSheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2']
                        ]
                    ]);
                }

                $row++;
            }

            // Add autofilter to parts sheet
            $partsSheet->setAutoFilter('A1:I' . ($row - 1));

            // Create Summary sheet
            $summarySheet = $spreadsheet->createSheet();
            $summarySheet->setTitle('Summary');
            $this->createSummarySheet($summarySheet, $inquiryRepository);

            // Create the writer
            $writer = new Xlsx($spreadsheet);

            // Create a streamed response
            $response = new StreamedResponse();
            $response->setCallback(function () use ($writer) {
                $writer->save('php://output');
            });

            // Set response headers
            $filename = 'inquiries_' . date('Y-m-d_H-i-s') . '.xlsx';
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error exporting inquiries to Excel: ' . $e->getMessage());
            throw $e;
        }
    }

    private function writeInquiryRow($sheet, $inquiry, $user, $machine, $part, int $row): void
    {
        $sheet->setCellValue('A' . $row, $inquiry->getId());
        $sheet->setCellValue('B' . $row, $inquiry->getInquiryNumber());
        $sheet->setCellValue('C' . $row, $user ? $user->getEmail() : '');
        $sheet->setCellValue('D' . $row, $inquiry->getStatus());
        $sheet->setCellValue('E' . $row, $inquiry->getContactEmail() ?? '');
        $sheet->setCellValue('F' . $row, $inquiry->getContactPhone() ?? '');
        $sheet->setCellValue('G' . $row, $inquiry->getCreatedAt() ? $inquiry->getCreatedAt()->format('Y-m-d H:i:s') : '');

        if ($machine) {
            $machineEntity = $machine->getMachine();
            $sheet->setCellValue('H' . $row, $machineEntity ? $machineEntity->getArticleDescription() : '');
            $sheet->setCellValue('I' . $row, $machine->getCustomMachineId() ?? '');
            $sheet->setCellValue('J' . $row, $machine->getNotes() ?? '');
        } else {
            $sheet->setCellValue('H' . $row, '');
            $sheet->setCellValue('I' . $row, '');
            $sheet->setCellValue('J' . $row, '');
        }

        if ($part) {
            $sheet->setCellValue('K' . $row, $part->getPartName());
            $sheet->setCellValue('L' . $row, $part->getPartNumber() ?? '');
            $sheet->setCellValue('M' . $row, $part->getShortDescription() ?? '');
            $sheet->setCellValue('N' . $row, $part->getAdditionalNotes() ?? '');
        } else {
            $sheet->setCellValue('K' . $row, '');
            $sheet->setCellValue('L' . $row, '');
            $sheet->setCellValue('M' . $row, '');
            $sheet->setCellValue('N' . $row, '');
        }

        // Apply borders to all cells
        $sheet->getStyle('A' . $row . ':N' . $row)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9']
                ]
            ]
        ]);
    }

    private function mergeInquiryCells($sheet, int $startRow, int $endRow): void
    {
        // Merge cells for inquiry-level data (columns A-G)
        $columnsToMerge = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        foreach ($columnsToMerge as $column) {
            $sheet->mergeCells("{$column}{$startRow}:{$column}{$endRow}");
            $sheet->getStyle("{$column}{$startRow}:{$column}{$endRow}")->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // Apply alternating row color to the entire inquiry block
        if ($startRow % 2 == 0) {
            $sheet->getStyle("A{$startRow}:N{$endRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F2F2F2']
                ]
            ]);
        }
    }

    private function createSummarySheet($sheet, InquiryRepository $inquiryRepository): void
    {
        $sheet->setCellValue('A1', 'Inquiry Export Summary');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A3', 'Export Date:');
        $sheet->setCellValue('B3', date('Y-m-d H:i:s'));

        // Calculate statistics (excluding draft inquiries)
        $totalInquiries = $inquiryRepository->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.status != :draftStatus')
            ->setParameter('draftStatus', 'draft')
            ->getQuery()
            ->getSingleScalarResult();

        // Count inquiries by status (excluding draft inquiries)
        $statusStats = $inquiryRepository->createQueryBuilder('i')
            ->select('i.status', 'COUNT(i.id) as count')
            ->where('i.status != :draftStatus')
            ->setParameter('draftStatus', 'draft')
            ->groupBy('i.status')
            ->getQuery()
            ->getResult();

        $sheet->setCellValue('A5', 'Total Inquiries:');
        $sheet->setCellValue('B5', $totalInquiries);

        // Inquiries by status
        $sheet->setCellValue('A7', 'Inquiries by Status');
        $sheet->getStyle('A7')->getFont()->setBold(true);

        $row = 8;
        foreach ($statusStats as $stat) {
            $sheet->setCellValue('A' . $row, $stat['status']);
            $sheet->setCellValue('B' . $row, $stat['count']);
            $row++;
        }

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
