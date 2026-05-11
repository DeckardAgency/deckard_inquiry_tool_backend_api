<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
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

class OrderExcelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    #[Route('/api/orders/export/excel', name: 'orders_export_excel', methods: ['GET'])]
    public function exportToExcel(
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository
    ): StreamedResponse {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300); // 5 minutes

            $spreadsheet = new Spreadsheet();

            // Create Orders sheet with items included
            $ordersSheet = $spreadsheet->getActiveSheet();
            $ordersSheet->setTitle('Orders');

            // Headers for orders
            $orderHeaders = [
                'Order ID',
                'Order Number',
                'User Email',
                'Status',
                'Created At',
                'Product ID',
                'Product Name',
                'Product Code',
                'Quantity'
            ];

            $this->writeHeaders($ordersSheet, $orderHeaders);

            // Fetch orders with user data and items (excluding draft orders)
            $query = $orderRepository->createQueryBuilder('o')
                ->leftJoin('o.user', 'u')
                ->leftJoin('o.items', 'i')
                ->leftJoin('i.product', 'p')
                ->select('o', 'u', 'i', 'p')
                ->where('o.status != :draftStatus')
                ->setParameter('draftStatus', 'draft')
                ->orderBy('o.orderNumber', 'ASC')
                ->addOrderBy('i.id', 'ASC')
                ->getQuery();

            $row = 2;
            $lastOrderId = null;
            $orderStartRow = 2;

            foreach ($query->getResult() as $order) {
                // Check if this is a new order
                if ($lastOrderId !== $order->getId()) {
                    // Apply merge styling to previous order if exists
                    if ($lastOrderId !== null && $row > $orderStartRow) {
                        $this->mergeOrderCells($ordersSheet, $orderStartRow, $row - 1);
                    }

                    $orderStartRow = $row;
                    $lastOrderId = $order->getId();
                }

                $user = $order->getUser();
                $items = $order->getItems();

                // If order has no items, write one row with empty item data
                if ($items->isEmpty()) {
                    $this->writeOrderRow($ordersSheet, $order, $user, null, $row);
                    $row++;
                } else {
                    // Write a row for each item
                    foreach ($items as $item) {
                        $this->writeOrderRow($ordersSheet, $order, $user, $item, $row);
                        $row++;
                    }
                }
            }

            // Apply merge styling to the last order
            if ($lastOrderId !== null && $row > $orderStartRow) {
                $this->mergeOrderCells($ordersSheet, $orderStartRow, $row - 1);
            }

            // Add autofilter
            $ordersSheet->setAutoFilter('A1:I' . ($row - 1));

            // Create separate Order Items sheet for detailed view
            $itemsSheet = $spreadsheet->createSheet();
            $itemsSheet->setTitle('Order Items Detail');

            $itemHeaders = [
                'Order Number',
                'User Email',
                'Product ID',
                'Product Name',
                'Product Code',
                'Quantity',
                'Item Created At'
            ];

            $this->writeHeaders($itemsSheet, $itemHeaders);

            // Fetch all order items with complete data (excluding draft orders)
            $itemsQuery = $orderItemRepository->createQueryBuilder('oi')
                ->join('oi.orderRef', 'o')
                ->join('oi.product', 'p')
                ->leftJoin('o.user', 'u')
                ->select('oi', 'o', 'p', 'u')
                ->where('o.status != :draftStatus')
                ->setParameter('draftStatus', 'draft')
                ->orderBy('o.orderNumber', 'ASC')
                ->getQuery();

            $row = 2;
            foreach ($itemsQuery->getResult() as $orderItem) {
                $order = $orderItem->getOrderRef();
                $product = $orderItem->getProduct();
                $user = $order->getUser();

                $itemsSheet->setCellValue('A' . $row, $order->getOrderNumber());
                $itemsSheet->setCellValue('B' . $row, $user ? $user->getEmail() : '');
                $itemsSheet->setCellValue('C' . $row, $product->getId());
                $itemsSheet->setCellValue('D' . $row, $product->getName());
                $itemsSheet->setCellValue('E' . $row, $product->getPartNo() ?? '');
                $itemsSheet->setCellValue('F' . $row, $orderItem->getQuantity());
                $itemsSheet->setCellValue('G' . $row, $orderItem->getCreatedAt() ? $orderItem->getCreatedAt()->format('Y-m-d H:i:s') : '');

                // Apply styling
                if ($row % 2 == 0) {
                    $itemsSheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2']
                        ]
                    ]);
                }

                $row++;
            }

            // Add autofilter to items sheet
            $itemsSheet->setAutoFilter('A1:G' . ($row - 1));

            // Create Summary sheet
            $summarySheet = $spreadsheet->createSheet();
            $summarySheet->setTitle('Summary');
            $this->createSummarySheet($summarySheet, $orderRepository);

            // Create the writer
            $writer = new Xlsx($spreadsheet);

            // Create a streamed response
            $response = new StreamedResponse();
            $response->setCallback(function () use ($writer) {
                $writer->save('php://output');
            });

            // Set response headers
            $filename = 'orders_' . date('Y-m-d_H-i-s') . '.xlsx';
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error exporting orders to Excel: ' . $e->getMessage());
            throw $e;
        }
    }

    private function writeOrderRow($sheet, $order, $user, $item, int $row): void
    {
        $sheet->setCellValue('A' . $row, $order->getId());
        $sheet->setCellValue('B' . $row, $order->getOrderNumber());
        $sheet->setCellValue('C' . $row, $user ? $user->getEmail() : '');
        $sheet->setCellValue('D' . $row, $order->getStatus());
        $sheet->setCellValue('E' . $row, $order->getCreatedAt() ? $order->getCreatedAt()->format('Y-m-d H:i:s') : '');

        if ($item) {
            $product = $item->getProduct();
            $sheet->setCellValue('F' . $row, $product->getId());
            $sheet->setCellValue('G' . $row, $product->getName());
            $sheet->setCellValue('H' . $row, $product->getPartNo() ?? '');
            $sheet->setCellValue('I' . $row, $item->getQuantity());
        } else {
            $sheet->setCellValue('F' . $row, '');
            $sheet->setCellValue('G' . $row, '');
            $sheet->setCellValue('H' . $row, '');
            $sheet->setCellValue('I' . $row, '');
        }

        // Apply borders to all cells
        $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9']
                ]
            ]
        ]);
    }

    private function mergeOrderCells($sheet, int $startRow, int $endRow): void
    {
        // Merge cells for order-level data (columns A-E)
        $columnsToMerge = ['A', 'B', 'C', 'D', 'E'];

        foreach ($columnsToMerge as $column) {
            $sheet->mergeCells("{$column}{$startRow}:{$column}{$endRow}");
            $sheet->getStyle("{$column}{$startRow}:{$column}{$endRow}")->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // Apply alternating row color to the entire order block
        if ($startRow % 2 == 0) {
            $sheet->getStyle("A{$startRow}:I{$endRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F2F2F2']
                ]
            ]);
        }
    }

    private function createSummarySheet($sheet, OrderRepository $orderRepository): void
    {
        $sheet->setCellValue('A1', 'Order Export Summary');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A3', 'Export Date:');
        $sheet->setCellValue('B3', date('Y-m-d H:i:s'));

        // Calculate statistics (excluding draft orders)
        $totalOrders = $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status != :draftStatus')
            ->setParameter('draftStatus', 'draft')
            ->getQuery()
            ->getSingleScalarResult();

        // Count orders by status (excluding draft orders)
        $statusStats = $orderRepository->createQueryBuilder('o')
            ->select('o.status', 'COUNT(o.id) as count')
            ->where('o.status != :draftStatus')
            ->setParameter('draftStatus', 'draft')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $sheet->setCellValue('A5', 'Total Orders:');
        $sheet->setCellValue('B5', $totalOrders);

        // Orders by status
        $sheet->setCellValue('A7', 'Orders by Status');
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
