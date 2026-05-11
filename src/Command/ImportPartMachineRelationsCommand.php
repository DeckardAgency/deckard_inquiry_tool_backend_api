<?php

namespace App\Command;

use App\Entity\Machine;
use App\Entity\MachineCategory;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-part-machine-relations',
    description: 'Links products to machines via category mappings from XLSX file'
)]
class ImportPartMachineRelationsCommand extends Command
{
    private const XLSX_PATH = 'src/Resources/machine_part_relations/part_machine_relations.xlsx';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing machine_product relations before import')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview import without writing to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Part–Machine Relations Import from XLSX');

        if ($dryRun) {
            $io->warning('DRY RUN MODE — no changes will be made to the database.');
        }

        // Check XLSX file
        $xlsxFilePath = $this->projectDir . '/' . self::XLSX_PATH;
        if (!file_exists($xlsxFilePath)) {
            $io->error(sprintf('XLSX file not found at: %s', $xlsxFilePath));
            return Command::FAILURE;
        }

        // Clear existing relations if requested
        if ($clear && !$dryRun) {
            $io->comment('Clearing existing machine_product relations...');
            $conn = $this->entityManager->getConnection();
            $deleted = $conn->executeStatement('DELETE FROM machine_product');
            $io->comment(sprintf('  → %d machine_product rows deleted', $deleted));
            $this->entityManager->clear();
        }

        // Pre-load all products indexed by partNo
        $io->comment('Loading products from database...');
        $products = $this->entityManager->getRepository(Product::class)->findAll();
        $productMap = [];
        foreach ($products as $product) {
            if ($product->getPartNo()) {
                $productMap[$product->getPartNo()] = $product;
            }
        }
        $io->info(sprintf('Loaded %d products', count($productMap)));

        // Pre-load all categories indexed by name
        $io->comment('Loading machine categories from database...');
        $categories = $this->entityManager->getRepository(MachineCategory::class)->findAll();
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->getName()] = $category;
        }
        $io->info(sprintf('Loaded %d categories', count($categoryMap)));

        // Pre-load all machines grouped by category
        $io->comment('Loading machines from database...');
        $machines = $this->entityManager->getRepository(Machine::class)->findAll();
        $machinesByCategoryName = [];
        foreach ($machines as $machine) {
            $cat = $machine->getCategory();
            if ($cat) {
                $catName = $cat->getName();
                if (!isset($machinesByCategoryName[$catName])) {
                    $machinesByCategoryName[$catName] = [];
                }
                $machinesByCategoryName[$catName][] = $machine;
            }
        }
        $io->info(sprintf('Loaded %d machines across %d categories', count($machines), count($machinesByCategoryName)));

        // Read XLSX
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $startTime = microtime(true);

        $io->comment('Loading spreadsheet...');

        try {
            $spreadsheet = $reader->load($xlsxFilePath);
            $relationsSheet = $spreadsheet->getSheetByName('Relations');

            if (!$relationsSheet) {
                $io->error('Sheet "Relations" not found in XLSX file.');
                return Command::FAILURE;
            }

            $highestRow = $relationsSheet->getHighestRow();
            $dataRows = $highestRow - 1;

            $io->section(sprintf('Processing %d relation rows', $dataRows));

            $totalRelations = 0;
            $totalSkippedNoProduct = 0;
            $totalSkippedNoMachines = 0;
            $totalLinksCreated = 0;
            $categoryStats = [];

            if (!$dryRun) {
                $this->entityManager->getConnection()->beginTransaction();
            }

            try {
                $progressBar = new ProgressBar($output, $dataRows);
                $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
                $progressBar->start();

                for ($row = 2; $row <= $highestRow; $row++) {
                    $partNo = trim((string) ($relationsSheet->getCell('A' . $row)->getValue() ?? ''));
                    $categoryName = trim((string) ($relationsSheet->getCell('B' . $row)->getValue() ?? ''));

                    if (empty($partNo) || empty($categoryName)) {
                        $progressBar->advance();
                        continue;
                    }

                    $totalRelations++;

                    // Find product
                    if (!isset($productMap[$partNo])) {
                        $totalSkippedNoProduct++;
                        $progressBar->advance();
                        continue;
                    }

                    $product = $productMap[$partNo];

                    // Find machines in this category
                    if (!isset($machinesByCategoryName[$categoryName]) || empty($machinesByCategoryName[$categoryName])) {
                        $totalSkippedNoMachines++;
                        if (!isset($categoryStats[$categoryName])) {
                            $categoryStats[$categoryName] = ['linked' => 0, 'no_machines' => 0];
                        }
                        $categoryStats[$categoryName]['no_machines']++;
                        $progressBar->advance();
                        continue;
                    }

                    if (!isset($categoryStats[$categoryName])) {
                        $categoryStats[$categoryName] = ['linked' => 0, 'no_machines' => 0];
                    }

                    $categoryMachines = $machinesByCategoryName[$categoryName];

                    if ($dryRun) {
                        $io->text(sprintf(
                            '  [DRY] %s → %s (%d machine(s))',
                            $partNo,
                            $categoryName,
                            count($categoryMachines)
                        ));
                    } else {
                        foreach ($categoryMachines as $machine) {
                            // Use the Machine entity's addProduct to handle the ManyToMany
                            if (!$machine->getProducts()->contains($product)) {
                                $machine->addProduct($product);
                                $totalLinksCreated++;
                            }
                        }
                    }

                    $categoryStats[$categoryName]['linked']++;
                    $progressBar->advance();
                }

                $progressBar->finish();
                $io->newLine();

                if (!$dryRun) {
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();
                }

                $io->newLine();
                $elapsedTime = microtime(true) - $startTime;

                // Summary table
                $io->section('Summary per category');
                $tableRows = [];
                ksort($categoryStats);
                foreach ($categoryStats as $catName => $stats) {
                    $machineCount = isset($machinesByCategoryName[$catName]) ? count($machinesByCategoryName[$catName]) : 0;
                    $tableRows[] = [$catName, $machineCount, $stats['linked'], $stats['no_machines']];
                }
                $io->table(['Category', 'Machines', 'Products Linked', 'No Machines (skipped)'], $tableRows);

                $io->success([
                    sprintf(
                        '%s — %d relation rows processed, %d machine-product links created',
                        $dryRun ? 'Would create' : 'Successfully created',
                        $totalRelations,
                        $dryRun ? 0 : $totalLinksCreated
                    ),
                    sprintf('Products not found: %d', $totalSkippedNoProduct),
                    sprintf('Categories with no machines: %d rows skipped', $totalSkippedNoMachines),
                    sprintf('Time: %.2f seconds', $elapsedTime),
                ]);

            } catch (\Exception $e) {
                if (!$dryRun) {
                    $this->entityManager->getConnection()->rollBack();
                }
                $io->error([
                    'Import failed!',
                    $e->getMessage(),
                    $e->getTraceAsString(),
                ]);

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error(['Error loading spreadsheet:', $e->getMessage()]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
