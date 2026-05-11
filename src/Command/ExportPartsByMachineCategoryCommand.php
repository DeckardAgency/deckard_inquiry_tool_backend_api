<?php

namespace App\Command;

use App\Entity\Machine;
use App\Entity\MachineCategory;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-parts-by-machine-category',
    description: 'Exports all products grouped into one XLSX sheet per machine category, with an Orphans sheet for products without machines/categories'
)]
class ExportPartsByMachineCategoryCommand extends Command
{
    private const ORPHAN_SHEET_TITLE = 'Orphans (no category)';
    private const MACHINES_SHEET_TITLE = 'Machines Overview';
    private const STATS_SHEET_TITLE = 'Statistics';

    private const HEADERS = [
        'Part No',
        'Name',
        'Short Description',
        'Unit',
        'Price',
        'Weight',
        'Statistic',
        'Machine Text',
        'Technical Description',
        'Associated Machines',
    ];

    private const MACHINE_HEADERS = [
        'Category',
        'Article Number',
        'Article Description',
        'IB Station No',
        'IB Serial No',
        'Order Number',
        'Delivery Date',
        'KMS Identification',
        'KMS ID',
        'MC Number',
        'Main Warranty End',
        'Extended Warranty End',
        'FI Station No',
        'FI Serial No',
        'Linked Parts',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (relative paths are resolved against the project directory)')
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'PHP memory limit for the export process', '1G')
            ->addOption('skip-empty', null, InputOption::VALUE_NONE, 'Skip category sheets (and Orphans) that have zero parts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', $input->getOption('memory-limit'));

        $io = new SymfonyStyle($input, $output);
        $startTime = microtime(true);
        $skipEmpty = (bool) $input->getOption('skip-empty');

        $io->title('Export Parts by Machine Category');

        if ($skipEmpty) {
            $io->comment('Skip-empty mode: category sheets with zero parts will be omitted.');
        }

        $outputPath = $this->resolveOutputPath($input->getOption('output'));
        $outputDir = dirname($outputPath);

        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            $io->error(sprintf('Failed to create output directory: %s', $outputDir));
            return Command::FAILURE;
        }

        $io->comment(sprintf('Output target: %s', $outputPath));
        $io->comment('Loading data...');

        $categories = $this->entityManager->getRepository(MachineCategory::class)
            ->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $products = $this->entityManager->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.machines', 'm')->addSelect('m')
            ->leftJoin('m.category', 'c')->addSelect('c')
            ->orderBy('p.partNo', 'ASC')
            ->getQuery()
            ->getResult();

        $io->info(sprintf('Loaded %d categories and %d products.', count($categories), count($products)));

        // Group products by category id; track orphans separately.
        $byCategory = [];
        foreach ($categories as $category) {
            $byCategory[(string) $category->getId()] = [
                'category' => $category,
                'products' => [],
                'seen' => [],
            ];
        }

        $orphans = [];
        $seenOrphan = [];

        foreach ($products as $product) {
            $productKey = (string) $product->getId();
            $machines = $product->getMachines();
            $categoryIds = [];

            foreach ($machines as $machine) {
                $cat = $machine->getCategory();
                if ($cat === null) {
                    continue;
                }
                $catKey = (string) $cat->getId();
                if (isset($byCategory[$catKey])) {
                    $categoryIds[$catKey] = true;
                }
            }

            if (empty($categoryIds)) {
                if (!isset($seenOrphan[$productKey])) {
                    $orphans[] = $product;
                    $seenOrphan[$productKey] = true;
                }
                continue;
            }

            foreach (array_keys($categoryIds) as $catKey) {
                if (!isset($byCategory[$catKey]['seen'][$productKey])) {
                    $byCategory[$catKey]['products'][] = $product;
                    $byCategory[$catKey]['seen'][$productKey] = true;
                }
            }
        }

        // Load all machines for the overview sheet (separate query so it includes machines without products too).
        $machines = $this->entityManager->getRepository(Machine::class)
            ->createQueryBuilder('m')
            ->leftJoin('m.category', 'c')->addSelect('c')
            ->leftJoin('m.products', 'p')->addSelect('p')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('m.articleDescription', 'ASC')
            ->addOrderBy('m.articleNumber', 'ASC')
            ->getQuery()
            ->getResult();

        $io->info(sprintf('Loaded %d machines for overview.', count($machines)));

        // Build spreadsheet
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $usedTitles = [];
        $sheetStats = [];
        $sheetIndex = 0;

        // Statistics sheet — first, so client sees the high-level picture immediately.
        $statsTitle = $this->uniqueSheetTitle(self::STATS_SHEET_TITLE, $usedTitles);
        $statsSheet = new Worksheet($spreadsheet, $statsTitle);
        $spreadsheet->addSheet($statsSheet, $sheetIndex++);
        $this->writeStatsSheet($statsSheet, $categories, $machines, $products, $byCategory, $orphans);
        $sheetStats[] = [$statsTitle, '—', '—'];

        // Machines Overview sheet — second, acts as the machine index.
        $overviewTitle = $this->uniqueSheetTitle(self::MACHINES_SHEET_TITLE, $usedTitles);
        $overviewSheet = new Worksheet($spreadsheet, $overviewTitle);
        $spreadsheet->addSheet($overviewSheet, $sheetIndex++);
        $overviewRows = $this->writeMachinesToSheet($overviewSheet, $machines);
        $sheetStats[] = [$overviewTitle, count($machines), $overviewRows];

        $skippedSheets = [];

        foreach ($byCategory as $entry) {
            /** @var MachineCategory $category */
            $category = $entry['category'];
            $partCount = count($entry['products']);

            if ($skipEmpty && $partCount === 0) {
                $skippedSheets[] = $category->getName() ?: 'Category';
                continue;
            }

            $title = $this->uniqueSheetTitle($category->getName() ?: 'Category', $usedTitles);

            $sheet = new Worksheet($spreadsheet, $title);
            $spreadsheet->addSheet($sheet, $sheetIndex++);

            $rowCount = $this->writeProductsToSheet($sheet, $entry['products']);
            $sheetStats[] = [$title, $partCount, $rowCount];
        }

        // Orphans sheet — included unless --skip-empty AND it's empty.
        if ($skipEmpty && count($orphans) === 0) {
            $skippedSheets[] = self::ORPHAN_SHEET_TITLE;
        } else {
            $orphanTitle = $this->uniqueSheetTitle(self::ORPHAN_SHEET_TITLE, $usedTitles);
            $orphanSheet = new Worksheet($spreadsheet, $orphanTitle);
            $spreadsheet->addSheet($orphanSheet, $sheetIndex);
            $orphanRowCount = $this->writeProductsToSheet($orphanSheet, $orphans);
            $sheetStats[] = [$orphanTitle, count($orphans), $orphanRowCount];
        }

        $spreadsheet->setActiveSheetIndex(0);

        $io->comment('Writing XLSX file...');
        $writer = new XlsxWriter($spreadsheet);
        $writer->save($outputPath);

        $elapsed = microtime(true) - $startTime;
        $memory = memory_get_peak_usage(true) / 1024 / 1024;

        $io->section('Summary');
        $io->table(['Sheet', 'Parts', 'Rows written'], $sheetStats);

        if (!empty($skippedSheets)) {
            $io->comment(sprintf('Skipped %d empty sheet(s): %s', count($skippedSheets), implode(', ', $skippedSheets)));
        }

        $io->success([
            sprintf('Exported to: %s', $outputPath),
            sprintf('Time: %.2f seconds', $elapsed),
            sprintf('Peak memory usage: %.2f MB', $memory),
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param Product[] $products
     */
    private function writeProductsToSheet(Worksheet $sheet, array $products): int
    {
        // Header row
        foreach (self::HEADERS as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }

        $headerRange = sprintf('A1:%s1', $sheet->getHighestColumn());
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE4E4E7');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($products as $product) {
            $machineLabels = [];
            foreach ($product->getMachines() as $machine) {
                $machineLabels[] = $this->formatMachineLabel($machine);
            }

            $values = [
                $product->getPartNo(),
                $product->getName(),
                $product->getShortDescription(),
                $product->getUnit(),
                $product->getPrice(),
                $product->getWeight(),
                $product->getStatistic(),
                $product->getMachineText(),
                $product->getTechnicalDescription(),
                implode(', ', $machineLabels),
            ];

            foreach ($values as $i => $value) {
                $sheet->setCellValue([$i + 1, $row], $value);
            }
            $row++;
        }

        // Auto-size columns up to a sensible cap; PhpSpreadsheet's autosize is expensive on huge sheets.
        for ($col = 1; $col <= count(self::HEADERS); $col++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        // Wrap text on long-text columns (Short Description, Machine Text, Technical Description, Associated Machines).
        foreach (['C', 'H', 'I', 'J'] as $col) {
            $sheet->getStyle(sprintf('%s2:%s%d', $col, $col, max($row - 1, 2)))
                ->getAlignment()->setWrapText(true);
        }

        return $row - 2;
    }

    /**
     * @param MachineCategory[] $categories
     * @param Machine[] $machines
     * @param Product[] $products
     * @param array<string, array{category: MachineCategory, products: Product[], seen: array}> $byCategory
     * @param Product[] $orphans
     */
    private function writeStatsSheet(
        Worksheet $sheet,
        array $categories,
        array $machines,
        array $products,
        array $byCategory,
        array $orphans,
    ): void {
        // ---- Pre-compute aggregates ----
        $machinesByCategory = [];
        $machinesWithoutCategory = 0;
        foreach ($machines as $machine) {
            $cat = $machine->getCategory();
            if ($cat === null) {
                $machinesWithoutCategory++;
                continue;
            }
            $key = (string) $cat->getId();
            $machinesByCategory[$key] = ($machinesByCategory[$key] ?? 0) + 1;
        }

        $productsWithoutMachine = 0;
        $productsWithMachineButNoCategory = 0;
        $totalLinks = 0;
        $pricesPositive = [];
        foreach ($products as $product) {
            $machineCount = count($product->getMachines());
            $totalLinks += $machineCount;

            if ($machineCount === 0) {
                $productsWithoutMachine++;
            } else {
                $hasCategory = false;
                foreach ($product->getMachines() as $m) {
                    if ($m->getCategory() !== null) {
                        $hasCategory = true;
                        break;
                    }
                }
                if (!$hasCategory) {
                    $productsWithMachineButNoCategory++;
                }
            }

            $price = (float) $product->getPrice();
            if ($price > 0) {
                $pricesPositive[] = $price;
            }
        }

        $minPrice = $pricesPositive ? min($pricesPositive) : 0;
        $maxPrice = $pricesPositive ? max($pricesPositive) : 0;
        $avgPrice = $pricesPositive ? array_sum($pricesPositive) / count($pricesPositive) : 0;

        // ---- Write sections ----
        $row = 1;

        // Title
        $sheet->setCellValue([1, $row], 'Parts & Machines — Data Verification Report');
        $sheet->mergeCells(sprintf('A%d:D%d', $row, $row));
        $sheet->getStyle(sprintf('A%d', $row))->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue([1, $row], sprintf('Generated: %s', date('Y-m-d H:i:s')));
        $sheet->getStyle(sprintf('A%d', $row))->getFont()->setItalic(true);
        $sheet->getStyle(sprintf('A%d', $row))->getFont()->getColor()->setARGB('FF71717A');
        $row += 2;

        // Section: Totals
        $row = $this->writeStatsSectionHeader($sheet, $row, 'Totals');
        $totals = [
            ['Categories', count($categories)],
            ['Machines', count($machines)],
            ['Parts', count($products)],
            ['Machine-Part links', $totalLinks],
        ];
        foreach ($totals as [$label, $value]) {
            $sheet->setCellValue([1, $row], $label);
            $sheet->setCellValue([2, $row], $value);
            $row++;
        }
        $row++;

        // Section: Coverage Issues
        $row = $this->writeStatsSectionHeader($sheet, $row, 'Coverage issues (review these)');
        $emptyCategories = 0;
        $categoriesWithNoProducts = 0;
        foreach ($byCategory as $entry) {
            if (count($entry['products']) === 0) {
                $categoriesWithNoProducts++;
            }
        }
        foreach ($categories as $category) {
            $key = (string) $category->getId();
            if (($machinesByCategory[$key] ?? 0) === 0) {
                $emptyCategories++;
            }
        }
        $coverage = [
            ['Machines without category', $machinesWithoutCategory],
            ['Parts without any machine', $productsWithoutMachine],
            ['Parts linked only to machines without category', $productsWithMachineButNoCategory],
            ['Total orphan parts (in Orphans sheet)', count($orphans)],
            ['Categories with no machines', $emptyCategories],
            ['Categories with no parts', $categoriesWithNoProducts],
        ];
        foreach ($coverage as [$label, $value]) {
            $sheet->setCellValue([1, $row], $label);
            $sheet->setCellValue([2, $row], $value);
            if ($value > 0) {
                $sheet->getStyle(sprintf('B%d', $row))->getFont()->setBold(true);
                $sheet->getStyle(sprintf('B%d', $row))->getFont()->getColor()->setARGB('FFDC2626');
            }
            $row++;
        }
        $row++;

        // Section: Pricing
        $row = $this->writeStatsSectionHeader($sheet, $row, 'Pricing (parts with price > 0)');
        $pricing = [
            ['Parts with price set', count($pricesPositive)],
            ['Parts without price', count($products) - count($pricesPositive)],
            ['Min price (€)', number_format($minPrice, 2, '.', '')],
            ['Avg price (€)', number_format($avgPrice, 2, '.', '')],
            ['Max price (€)', number_format($maxPrice, 2, '.', '')],
        ];
        foreach ($pricing as [$label, $value]) {
            $sheet->setCellValue([1, $row], $label);
            $sheet->setCellValue([2, $row], $value);
            $row++;
        }
        $row += 2;

        // Section: Per-Category Breakdown
        $row = $this->writeStatsSectionHeader($sheet, $row, 'Per-category breakdown');

        $tableHeader = ['Category', 'Machines', 'Parts', 'Avg Parts per Machine'];
        foreach ($tableHeader as $i => $h) {
            $sheet->setCellValue([$i + 1, $row], $h);
        }
        $sheet->getStyle(sprintf('A%d:D%d', $row, $row))->getFont()->setBold(true);
        $sheet->getStyle(sprintf('A%d:D%d', $row, $row))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE4E4E7');
        $row++;

        foreach ($byCategory as $catKey => $entry) {
            /** @var MachineCategory $category */
            $category = $entry['category'];
            $machineCount = $machinesByCategory[$catKey] ?? 0;
            $productCount = count($entry['products']);
            $avg = $machineCount > 0 ? $productCount / $machineCount : 0;

            $sheet->setCellValue([1, $row], $category->getName());
            $sheet->setCellValue([2, $row], $machineCount);
            $sheet->setCellValue([3, $row], $productCount);
            $sheet->setCellValue([4, $row], number_format($avg, 2, '.', ''));
            $row++;
        }

        // Orphans row
        $sheet->setCellValue([1, $row], '— (no category)');
        $sheet->setCellValue([2, $row], $machinesWithoutCategory);
        $sheet->setCellValue([3, $row], count($orphans));
        $sheet->setCellValue([4, $row], $machinesWithoutCategory > 0 ? number_format(count($orphans) / $machinesWithoutCategory, 2, '.', '') : '—');
        $sheet->getStyle(sprintf('A%d:D%d', $row, $row))->getFont()->setItalic(true);

        // Style: section A column wider, autosize
        $sheet->getColumnDimension('A')->setWidth(48);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(28);
    }

    private function writeStatsSectionHeader(Worksheet $sheet, int $row, string $title): int
    {
        $sheet->setCellValue([1, $row], $title);
        $sheet->mergeCells(sprintf('A%d:D%d', $row, $row));
        $sheet->getStyle(sprintf('A%d', $row))->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle(sprintf('A%d', $row))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF18181B');
        $sheet->getStyle(sprintf('A%d', $row))->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->getStyle(sprintf('A%d', $row))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        return $row + 1;
    }

    /**
     * @param Machine[] $machines
     */
    private function writeMachinesToSheet(Worksheet $sheet, array $machines): int
    {
        foreach (self::MACHINE_HEADERS as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }

        $headerRange = sprintf('A1:%s1', $sheet->getHighestColumn());
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE4E4E7');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($machines as $machine) {
            $category = $machine->getCategory();

            $values = [
                $category?->getName() ?? '— (no category)',
                $machine->getArticleNumber(),
                $machine->getArticleDescription(),
                $machine->getIbStationNumber(),
                $machine->getIbSerialNumber(),
                $machine->getOrderNumber(),
                $this->formatDate($machine->getDeliveryDate()),
                $machine->getKmsIdentificationNumber(),
                $machine->getKmsIdNumber(),
                $machine->getMcNumber(),
                $this->formatDate($machine->getMainWarrantyEnd()),
                $this->formatDate($machine->getExtendedWarrantyEnd()),
                $machine->getFiStationNumber(),
                $machine->getFiSerialNumber(),
                count($machine->getProducts()),
            ];

            foreach ($values as $i => $value) {
                $sheet->setCellValue([$i + 1, $row], $value);
            }
            $row++;
        }

        for ($col = 1; $col <= count(self::MACHINE_HEADERS); $col++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        return $row - 2;
    }

    private function formatDate(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    private function formatMachineLabel(Machine $machine): string
    {
        $parts = [];
        if ($machine->getArticleNumber()) {
            $parts[] = $machine->getArticleNumber();
        }
        if ($machine->getArticleDescription()) {
            $parts[] = $machine->getArticleDescription();
        }
        if (empty($parts)) {
            $parts[] = sprintf('Machine #%s', $machine->getId());
        }

        return implode(' — ', $parts);
    }

    /**
     * Excel limits sheet titles to 31 chars and disallows : \ / ? * [ ].
     * Also enforce uniqueness within the workbook.
     */
    private function uniqueSheetTitle(string $raw, array &$usedTitles): string
    {
        $clean = preg_replace('/[:\\\\\/\?\*\[\]]/', '-', $raw) ?? $raw;
        $clean = trim($clean);
        if ($clean === '') {
            $clean = 'Sheet';
        }
        if (mb_strlen($clean) > 31) {
            $clean = mb_substr($clean, 0, 31);
        }

        $candidate = $clean;
        $suffix = 2;
        while (isset($usedTitles[mb_strtolower($candidate)])) {
            $suffixStr = ' ' . $suffix;
            $candidate = mb_substr($clean, 0, 31 - mb_strlen($suffixStr)) . $suffixStr;
            $suffix++;
        }

        $usedTitles[mb_strtolower($candidate)] = true;
        return $candidate;
    }

    private function resolveOutputPath(?string $option): string
    {
        if ($option !== null && $option !== '') {
            if (str_starts_with($option, '/')) {
                return $option;
            }
            return $this->projectDir . '/' . ltrim($option, '/');
        }

        $timestamp = date('Ymd-His');
        return sprintf('%s/var/exports/parts-by-machine-category-%s.xlsx', $this->projectDir, $timestamp);
    }
}
