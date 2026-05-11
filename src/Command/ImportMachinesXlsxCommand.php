<?php

namespace App\Command;

use App\Entity\Machine;
use App\Entity\MachineCategory;
use App\Entity\MediaItem;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\MimeTypes;

#[AsCommand(
    name: 'app:import-machines-xlsx',
    description: 'Imports machines and machine categories from XLSX file'
)]
class ImportMachinesXlsxCommand extends Command
{
    private const XLSX_PATH = 'src/Resources/machines/machines.xlsx';
    private const IMAGES_DIR = 'src/Resources/machines_images';
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private array $mediaItemCache = [];
    private array $mimeTypeCache = [];
    private string $uploadDirectory;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $projectDir,
        private ?string $uploadDir = null
    ) {
        parent::__construct();
        $this->uploadDirectory = $uploadDir ?? $projectDir . '/public/uploads';
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Number of records to process in a batch', 50)
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'PHP memory limit for the import process', '1G')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete all existing machines and categories before import')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview import without writing to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', $input->getOption('memory-limit'));

        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Machines & Categories Import from XLSX');

        if ($dryRun) {
            $io->warning('DRY RUN MODE — no changes will be made to the database or filesystem.');
        }

        // Check XLSX file
        $xlsxFilePath = $this->projectDir . '/' . self::XLSX_PATH;
        if (!file_exists($xlsxFilePath)) {
            $io->error(sprintf('XLSX file not found at: %s', $xlsxFilePath));
            return Command::FAILURE;
        }

        // Check images directory
        $imagesDir = $this->projectDir . '/' . self::IMAGES_DIR;
        if (!is_dir($imagesDir)) {
            $io->warning(sprintf('Images directory not found at: %s. Machines will be imported without images.', $imagesDir));
            $imagesDir = null;
        }

        // Clear existing data if requested
        if ($clear && !$dryRun) {
            $io->comment('Clearing existing machines and categories...');
            $conn = $this->entityManager->getConnection();

            $conn->executeStatement('DELETE FROM machine_product');
            $io->comment('  → machine_product rows deleted');

            $conn->executeStatement('DELETE FROM client_machine_installed_base');
            $io->comment('  → client_machine_installed_base rows deleted');

            $conn->executeStatement('DELETE FROM media_item WHERE machine_id IS NOT NULL OR machine_document_id IS NOT NULL');
            $io->comment('  → machine media_item rows deleted');

            $conn->executeStatement('DELETE FROM machine');
            $io->comment('  → machine rows deleted');

            $conn->executeStatement('DELETE FROM machine_category');
            $io->comment('  → machine_category rows deleted');

            $this->entityManager->clear();
            $io->comment('All existing machines and categories cleared.');
        }

        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $startTime = microtime(true);

        $io->comment('Loading spreadsheet...');

        try {
            $spreadsheet = $reader->load($xlsxFilePath);
            $mimeTypesGuesser = new MimeTypes();

            if (!$dryRun) {
                $this->entityManager->getConnection()->beginTransaction();
            }

            try {
                // ── Step 1: Import Categories ──
                $categoriesSheet = $spreadsheet->getSheetByName('Categories');
                if (!$categoriesSheet) {
                    $io->error('Sheet "Categories" not found in XLSX file.');
                    return Command::FAILURE;
                }

                $highestRow = $categoriesSheet->getHighestRow();
                $io->section(sprintf('Importing categories — %d rows', $highestRow - 1));

                $categoryMap = []; // name => MachineCategory entity
                $totalCategories = 0;

                for ($row = 2; $row <= $highestRow; $row++) {
                    $name = trim((string) ($categoriesSheet->getCell('A' . $row)->getValue() ?? ''));
                    $description = trim((string) ($categoriesSheet->getCell('B' . $row)->getValue() ?? ''));

                    if (empty($name)) {
                        continue;
                    }

                    if ($dryRun) {
                        $io->text(sprintf('  [DRY] Category: %s — %s', $name, $description));
                        $totalCategories++;
                        continue;
                    }

                    // Find existing or create new
                    $category = $this->entityManager->getRepository(MachineCategory::class)
                        ->findOneBy(['name' => $name]);

                    if (!$category) {
                        $category = new MachineCategory();
                        $category->setName($name);
                    }

                    $category->setDescription(!empty($description) ? $description : null);

                    $this->entityManager->persist($category);
                    $categoryMap[$name] = $category;
                    $totalCategories++;
                }

                if (!$dryRun) {
                    $this->entityManager->flush();
                }

                $io->comment(sprintf('  → %d categories %s', $totalCategories, $dryRun ? 'found' : 'imported'));

                // ── Step 2: Import Machines ──
                $machinesSheet = $spreadsheet->getSheetByName('Machines');
                if (!$machinesSheet) {
                    $io->error('Sheet "Machines" not found in XLSX file.');
                    return Command::FAILURE;
                }

                $highestRow = $machinesSheet->getHighestRow();
                $dataRows = $highestRow - 1;
                $io->section(sprintf('Importing machines — %d rows', $dataRows));

                $totalMachines = 0;
                $totalMediaItems = 0;

                $progressBar = new ProgressBar($output, $dataRows);
                $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
                $progressBar->start();

                // Column mapping: A=articleNumber, B=articleDescription, C=ibStationNumber, D=ibSerialNumber,
                // E=orderNumber, F=deliveryDate, G=kmsIdentificationNumber, H=kmsIdNumber, I=mcNumber,
                // J=mainWarrantyEnd, K=extendedWarrantyEnd, L=fiStationNumber, M=fiSerialNumber,
                // N=categoryName, O=featuredImage

                for ($row = 2; $row <= $highestRow; $row++) {
                    $articleNumber = trim((string) ($machinesSheet->getCell('A' . $row)->getValue() ?? ''));

                    if (empty($articleNumber)) {
                        $progressBar->advance();
                        continue;
                    }

                    $articleDescription = trim((string) ($machinesSheet->getCell('B' . $row)->getValue() ?? ''));
                    $ibStationNumber = $machinesSheet->getCell('C' . $row)->getValue();
                    $ibSerialNumber = $machinesSheet->getCell('D' . $row)->getValue();
                    $orderNumber = trim((string) ($machinesSheet->getCell('E' . $row)->getValue() ?? ''));
                    $deliveryDateStr = trim((string) ($machinesSheet->getCell('F' . $row)->getValue() ?? ''));
                    $kmsIdentificationNumber = trim((string) ($machinesSheet->getCell('G' . $row)->getValue() ?? ''));
                    $kmsIdNumber = trim((string) ($machinesSheet->getCell('H' . $row)->getValue() ?? ''));
                    $mcNumber = trim((string) ($machinesSheet->getCell('I' . $row)->getValue() ?? ''));
                    $mainWarrantyEndStr = trim((string) ($machinesSheet->getCell('J' . $row)->getValue() ?? ''));
                    $extendedWarrantyEndStr = trim((string) ($machinesSheet->getCell('K' . $row)->getValue() ?? ''));
                    $fiStationNumber = $machinesSheet->getCell('L' . $row)->getValue();
                    $fiSerialNumber = $machinesSheet->getCell('M' . $row)->getValue();
                    $categoryName = trim((string) ($machinesSheet->getCell('N' . $row)->getValue() ?? ''));
                    $featuredImageFile = trim((string) ($machinesSheet->getCell('O' . $row)->getValue() ?? ''));

                    if ($dryRun) {
                        $io->text(sprintf(
                            '  [DRY] %s — %s (category: %s)',
                            $articleNumber,
                            $articleDescription,
                            $categoryName ?: 'none'
                        ));
                        $totalMachines++;
                        $progressBar->advance();
                        continue;
                    }

                    $machine = new Machine();
                    $machine->setArticleNumber($articleNumber);
                    $machine->setArticleDescription(!empty($articleDescription) ? $articleDescription : null);

                    if ($ibStationNumber !== null && $ibStationNumber !== '') {
                        $machine->setIbStationNumber((int) $ibStationNumber);
                    }
                    if ($ibSerialNumber !== null && $ibSerialNumber !== '') {
                        $machine->setIbSerialNumber((int) $ibSerialNumber);
                    }

                    $machine->setOrderNumber(!empty($orderNumber) ? $orderNumber : null);

                    if (!empty($deliveryDateStr)) {
                        try {
                            $machine->setDeliveryDate(new \DateTime($deliveryDateStr));
                        } catch (\Exception $e) {
                            // Skip invalid dates
                        }
                    }

                    $machine->setKmsIdentificationNumber(!empty($kmsIdentificationNumber) ? $kmsIdentificationNumber : null);
                    $machine->setKmsIdNumber(!empty($kmsIdNumber) ? $kmsIdNumber : null);
                    $machine->setMcNumber(!empty($mcNumber) ? $mcNumber : null);

                    if (!empty($mainWarrantyEndStr)) {
                        try {
                            $machine->setMainWarrantyEnd(new \DateTime($mainWarrantyEndStr));
                        } catch (\Exception $e) {
                            // Skip invalid dates
                        }
                    }

                    if (!empty($extendedWarrantyEndStr)) {
                        try {
                            $machine->setExtendedWarrantyEnd(new \DateTime($extendedWarrantyEndStr));
                        } catch (\Exception $e) {
                            // Skip invalid dates
                        }
                    }

                    if ($fiStationNumber !== null && $fiStationNumber !== '') {
                        $machine->setFiStationNumber((int) $fiStationNumber);
                    }
                    if ($fiSerialNumber !== null && $fiSerialNumber !== '') {
                        $machine->setFiSerialNumber((int) $fiSerialNumber);
                    }

                    // Assign category
                    if (!empty($categoryName)) {
                        if (isset($categoryMap[$categoryName])) {
                            $machine->setCategory($categoryMap[$categoryName]);
                        } else {
                            // Try finding in DB
                            $cat = $this->entityManager->getRepository(MachineCategory::class)
                                ->findOneBy(['name' => $categoryName]);
                            if ($cat) {
                                $categoryMap[$categoryName] = $cat;
                                $machine->setCategory($cat);
                            } else {
                                $io->warning(sprintf('Category not found for machine %s: %s', $articleNumber, $categoryName));
                            }
                        }
                    }

                    // Handle featured image
                    if (!empty($featuredImageFile) && $imagesDir) {
                        $fullImagePath = $imagesDir . '/' . $featuredImageFile;

                        $mediaItem = $this->getOrCreateMediaItem($fullImagePath, $featuredImageFile, $mimeTypesGuesser, $io);

                        if ($mediaItem) {
                            $machine->setFeaturedImage($mediaItem);
                            $totalMediaItems++;
                        }
                    }

                    $this->entityManager->persist($machine);
                    $totalMachines++;
                    $progressBar->advance();

                    // Batch flush
                    if (($totalMachines % $batchSize) === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        $this->refreshMediaItemCache();
                        $this->refreshCategoryCache($categoryMap);

                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                }

                $progressBar->finish();
                $io->newLine();

                if (!$dryRun) {
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();
                }

                $io->newLine();
                $elapsedTime = microtime(true) - $startTime;
                $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;

                $io->success([
                    sprintf(
                        '%s %d categories and %d machines with %d media items',
                        $dryRun ? 'Would import' : 'Successfully imported',
                        $totalCategories,
                        $totalMachines,
                        $totalMediaItems
                    ),
                    sprintf('Time: %.2f seconds', $elapsedTime),
                    sprintf('Peak memory usage: %.2f MB', $memoryUsage),
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

    private function refreshCategoryCache(array &$categoryMap): void
    {
        $names = array_keys($categoryMap);

        if (empty($names)) {
            return;
        }

        $categories = $this->entityManager->getRepository(MachineCategory::class)
            ->createQueryBuilder('c')
            ->where('c.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->getName()] = $category;
        }
    }

    private function refreshMediaItemCache(): void
    {
        $cachedPaths = array_keys($this->mediaItemCache);

        if (empty($cachedPaths)) {
            return;
        }

        $mediaItems = $this->entityManager->getRepository(MediaItem::class)
            ->createQueryBuilder('m')
            ->where('m.filePath IN (:paths)')
            ->setParameter('paths', $cachedPaths)
            ->getQuery()
            ->getResult();

        $this->mediaItemCache = [];

        foreach ($mediaItems as $mediaItem) {
            $this->mediaItemCache[$mediaItem->getFilePath()] = $mediaItem;
        }
    }

    private function getOrCreateMediaItem(string $fullImagePath, string $imagePath, MimeTypes $mimeTypesGuesser, SymfonyStyle $io): ?MediaItem
    {
        if (!file_exists($fullImagePath)) {
            $io->warning(sprintf('Image not found: %s', $fullImagePath));
            return null;
        }

        $fileHash = md5_file($fullImagePath);

        if (isset($this->mediaItemCache[$fileHash])) {
            return $this->mediaItemCache[$fileHash];
        }

        try {
            $originalFilename = pathinfo($fullImagePath, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($fullImagePath, PATHINFO_EXTENSION));

            $safeFilename = $this->slugify($originalFilename);
            $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid(), $extension);

            $uploadsDir = $this->uploadDirectory;

            if (!is_dir($uploadsDir)) {
                if (!mkdir($uploadsDir, 0755, true)) {
                    $io->error(sprintf('Failed to create uploads directory: %s', $uploadsDir));
                    return null;
                }
            }

            $destinationPath = $uploadsDir . '/' . $newFilename;

            if (isset($this->mimeTypeCache[$extension])) {
                $mimeType = $this->mimeTypeCache[$extension];
            } else {
                $mimeType = $mimeTypesGuesser->guessMimeType($fullImagePath);

                if (!$mimeType) {
                    $mimeType = match ($extension) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        default => 'image/jpeg',
                    };
                }

                $this->mimeTypeCache[$extension] = $mimeType;
            }

            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                $io->warning(sprintf('Unsupported image type for %s: %s', $originalFilename, $mimeType));
                return null;
            }

            if (!copy($fullImagePath, $destinationPath)) {
                $io->error(sprintf('Failed to copy image from %s to %s', $fullImagePath, $destinationPath));
                return null;
            }

            $mediaItem = new MediaItem();
            $mediaItem
                ->setFilename($newFilename)
                ->setMimeType($mimeType)
                ->setFilePath('/uploads/' . $newFilename);

            $this->entityManager->persist($mediaItem);

            $this->mediaItemCache[$fileHash] = $mediaItem;

            return $mediaItem;
        } catch (\Exception $e) {
            $io->error(sprintf('Error processing image %s: %s', $fullImagePath, $e->getMessage()));
            return null;
        }
    }

    private function slugify(string $string): string
    {
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
        $string = preg_replace('~[^\pL\d]+~u', '-', $string);
        $string = trim($string, '-');
        $string = preg_replace('~-+~', '-', $string);
        $string = strtolower($string);

        return empty($string) ? 'n-a' : $string;
    }
}
