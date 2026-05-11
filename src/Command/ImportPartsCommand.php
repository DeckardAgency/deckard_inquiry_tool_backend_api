<?php

namespace App\Command;

use App\Entity\Product;
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
    name: 'app:import-parts',
    description: 'Imports parts from XLSX file (sheets 1-5) with images auto-discovered from parts_images folder'
)]
class ImportPartsCommand extends Command
{
    private const XLSX_PATH = 'src/Resources/parts/parts.xlsx';
    private const IMAGES_DIR = 'src/Resources/parts/parts_images';

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
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete all existing products before import')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview import without writing to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', $input->getOption('memory-limit'));

        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Parts Import from XLSX');

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
            $io->error(sprintf('Images directory not found at: %s', $imagesDir));
            return Command::FAILURE;
        }

        // Clear existing products if requested
        if ($clear && !$dryRun) {
            $io->comment('Clearing existing products and related data...');
            $conn = $this->entityManager->getConnection();

            // Delete in correct order to respect foreign key constraints
            $conn->executeStatement('DELETE FROM order_item WHERE product_id IS NOT NULL');
            $io->comment('  → order_item rows deleted');

            $conn->executeStatement('DELETE FROM client_product_price');
            $io->comment('  → client_product_price rows deleted');

            $conn->executeStatement('DELETE FROM machine_product');
            $io->comment('  → machine_product rows deleted');

            $conn->executeStatement('DELETE FROM media_item WHERE product_id IS NOT NULL OR product_document_id IS NOT NULL');
            $io->comment('  → product media_item rows deleted');

            $conn->executeStatement('DELETE FROM product');
            $io->comment('  → product rows deleted');

            // Clear entity manager to avoid stale references
            $this->entityManager->clear();
            $io->comment('All existing products and related data cleared.');
        }

        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $startTime = microtime(true);

        $io->comment('Loading spreadsheet...');

        try {
            $spreadsheet = $reader->load($xlsxFilePath);
            $sheetNames = $spreadsheet->getSheetNames();
            $sheetCount = count($sheetNames);

            $io->info(sprintf('Found %d sheets: %s', $sheetCount, implode(', ', $sheetNames)));
            $io->comment('Skipping sheet "All" (index 0). Processing sheets 1–' . ($sheetCount - 1) . '.');

            $mimeTypesGuesser = new MimeTypes();
            $importedPartNos = [];
            $totalProducts = 0;
            $totalMediaItems = 0;
            $totalDuplicates = 0;
            $sheetStats = [];

            if (!$dryRun) {
                $this->entityManager->getConnection()->beginTransaction();
            }

            try {
                // Iterate sheets 1–5 (skip "All" at index 0)
                for ($sheetIndex = 1; $sheetIndex < $sheetCount; $sheetIndex++) {
                    $worksheet = $spreadsheet->getSheet($sheetIndex);
                    $sheetName = $worksheet->getTitle();
                    $highestRow = $worksheet->getHighestRow();
                    $dataRows = $highestRow - 1;

                    $io->section(sprintf('Sheet "%s" — %d data rows', $sheetName, $dataRows));

                    $sheetProducts = 0;
                    $sheetMedia = 0;
                    $sheetDuplicates = 0;

                    $progressBar = new ProgressBar($output, $dataRows);
                    $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
                    $progressBar->start();

                    for ($row = 2; $row <= $highestRow; $row++) {
                        $partNo = trim((string) ($worksheet->getCell('A' . $row)->getValue() ?? ''));

                        // Skip empty rows
                        if (empty($partNo)) {
                            $progressBar->advance();
                            continue;
                        }

                        // Skip duplicates across sheets
                        if (isset($importedPartNos[$partNo])) {
                            $sheetDuplicates++;
                            $totalDuplicates++;
                            $progressBar->advance();
                            continue;
                        }

                        $importedPartNos[$partNo] = $sheetName;

                        // Parse price
                        $priceStr = $worksheet->getCell('G' . $row)->getValue() ?? '0';
                        if (is_string($priceStr)) {
                            $price = (float) preg_replace('/[^0-9,.]/', '', str_replace(',', '.', $priceStr));
                        } else {
                            $price = (float) $priceStr;
                        }

                        // Parse weight as string
                        $weight = trim((string) ($worksheet->getCell('H' . $row)->getValue() ?? ''));

                        if ($dryRun) {
                            $io->text(sprintf(
                                '  [DRY] %s — %s (€%.2f)',
                                $partNo,
                                trim((string) ($worksheet->getCell('C' . $row)->getValue() ?? 'N/A')),
                                $price
                            ));

                            // Count images that would be found
                            $imageFiles = glob($imagesDir . '/' . strtolower($partNo) . '_*.jpg');
                            if ($imageFiles) {
                                sort($imageFiles);
                                $io->text(sprintf('       → %d image(s) found', count($imageFiles)));
                                $sheetMedia += count($imageFiles);
                            }

                            $sheetProducts++;
                            $progressBar->advance();
                            continue;
                        }

                        // Create Product entity
                        $product = new Product();
                        $product
                            ->setPartNo($partNo)
                            ->setName($partNo)
                            ->setShortDescription($worksheet->getCell('C' . $row)->getValue())
                            ->setUnit($worksheet->getCell('D' . $row)->getValue())
                            ->setStatistic($worksheet->getCell('E' . $row)->getValue())
                            ->setPrice($price)
                            ->setWeight(!empty($weight) ? $weight : null)
                            ->setMachineText($worksheet->getCell('I' . $row)->getValue())
                            ->setTechnicalDescription($worksheet->getCell('J' . $row)->getValue());

                        // Auto-discover images from filesystem
                        $imageFiles = glob($imagesDir . '/' . strtolower($partNo) . '_*.jpg');
                        if ($imageFiles) {
                            sort($imageFiles);

                            $processedMediaItems = [];
                            foreach ($imageFiles as $imageFile) {
                                $mediaItem = $this->getOrCreateMediaItem(
                                    $imageFile,
                                    basename($imageFile),
                                    $mimeTypesGuesser,
                                    $io
                                );

                                if ($mediaItem) {
                                    $processedMediaItems[] = $mediaItem;
                                    $sheetMedia++;
                                    $totalMediaItems++;
                                }
                            }

                            // First image = featured, rest = gallery
                            if (!empty($processedMediaItems)) {
                                $product->setFeaturedImage($processedMediaItems[0]);

                                for ($i = 1; $i < count($processedMediaItems); $i++) {
                                    $product->addImageGallery($processedMediaItems[$i]);
                                }
                            }
                        }

                        $this->entityManager->persist($product);
                        $sheetProducts++;
                        $totalProducts++;
                        $progressBar->advance();

                        // Batch flush
                        if (($totalProducts % $batchSize) === 0) {
                            $this->entityManager->flush();
                            $this->entityManager->clear();
                            $this->refreshMediaItemCache();

                            if (function_exists('gc_collect_cycles')) {
                                gc_collect_cycles();
                            }
                        }
                    }

                    $progressBar->finish();
                    $io->newLine();

                    $sheetStats[$sheetName] = [
                        'products' => $sheetProducts,
                        'media' => $sheetMedia,
                        'duplicates' => $sheetDuplicates,
                    ];

                    $io->comment(sprintf(
                        '  → %d products, %d images, %d duplicates skipped',
                        $sheetProducts,
                        $sheetMedia,
                        $sheetDuplicates
                    ));
                }

                if (!$dryRun) {
                    // Flush remaining
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();
                }

                $io->newLine();
                $elapsedTime = microtime(true) - $startTime;
                $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;

                // Summary table
                $io->section('Summary per sheet');
                $tableRows = [];
                foreach ($sheetStats as $name => $stats) {
                    $tableRows[] = [$name, $stats['products'], $stats['media'], $stats['duplicates']];
                }
                $io->table(['Sheet', 'Products', 'Images', 'Duplicates'], $tableRows);

                $io->success([
                    sprintf(
                        '%s %d products with %d media items (%d duplicates skipped)',
                        $dryRun ? 'Would import' : 'Successfully imported',
                        $dryRun ? array_sum(array_column($sheetStats, 'products')) : $totalProducts,
                        $dryRun ? array_sum(array_column($sheetStats, 'media')) : $totalMediaItems,
                        $totalDuplicates
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
