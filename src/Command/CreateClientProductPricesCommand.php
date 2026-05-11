<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\Client;
use App\Entity\ClientProductPrice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-client-prices',
    description: 'Creates custom product prices for all clients and products'
)]
class CreateClientProductPricesCommand extends Command
{
    // Min and max price range
    private const MIN_PRICE = 50;
    private const MAX_PRICE = 2500;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear-existing', null, InputOption::VALUE_NONE, 'Clear existing client product prices before creating new ones')
            ->addOption('skip-existing', 's', InputOption::VALUE_NONE, 'Skip creating prices that already exist (default: overwrite)')
            ->addOption('client-code', 'c', InputOption::VALUE_OPTIONAL, 'Only process a specific client by code')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for processing products', 20)
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'PHP memory limit for the process', '512M');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Set memory limit
        ini_set('memory_limit', $input->getOption('memory-limit'));

        $io = new SymfonyStyle($input, $output);
        $io->title('Create Client Product Prices');

        $batchSize = (int)$input->getOption('batch-size');
        $clearExisting = $input->getOption('clear-existing');
        $skipExisting = $input->getOption('skip-existing');
        $clientCode = $input->getOption('client-code');

        // Clear existing prices if requested
        if ($clearExisting) {
            if ($clientCode) {
                $io->comment(sprintf('Clearing existing prices for client: %s', $clientCode));
                $this->clearPricesForClient($clientCode, $io);
            } else {
                $io->comment('Clearing all existing client product prices...');
                $this->clearAllPrices($io);
            }
        }

        // Get clients to process
        if ($clientCode) {
            $client = $this->entityManager->getRepository(Client::class)
                ->findOneBy(['code' => $clientCode]);

            if (!$client) {
                $io->error(sprintf('Client with code "%s" not found', $clientCode));
                return Command::FAILURE;
            }

            $clients = [$client];
            $io->comment(sprintf('Processing only client: %s', $client->getName()));
        } else {
            $clients = $this->entityManager->getRepository(Client::class)->findAll();
            $io->comment(sprintf('Found %d clients to process', count($clients)));
        }

        if (empty($clients)) {
            $io->error('No clients found. Please create clients first.');
            return Command::FAILURE;
        }

        // Get total product count for progress bar
        $productCount = $this->entityManager->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($productCount === 0) {
            $io->error('No products found. Please import products first.');
            return Command::FAILURE;
        }

        $io->comment(sprintf('Found %d products to process', $productCount));

        // Process each client
        $totalPrices = 0;
        $totalClients = count($clients);

        foreach ($clients as $index => $client) {
            $clientId = $client->getId()->__toString();
            $io->section(sprintf('Processing client %d/%d: %s', $index + 1, $totalClients, $client->getName()));

            try {
                // Start a new transaction for each client
                $this->entityManager->getConnection()->beginTransaction();

                // Make sure we have a fresh client entity
                $client = $this->entityManager->getRepository(Client::class)->find($clientId);

                // Create prices for this client
                $pricesCreated = $this->createPricesForClient($client, $index, $productCount, $batchSize, $skipExisting, $io);
                $totalPrices += $pricesCreated;

                // Commit transaction
                $this->entityManager->getConnection()->commit();
                $io->success(sprintf('Created %d prices for client: %s', $pricesCreated, $client->getName()));

            } catch (\Exception $e) {
                // Rollback on error
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->getConnection()->rollBack();
                }

                $io->error([
                    sprintf('Error processing client %s:', $client->getName()),
                    $e->getMessage()
                ]);
            }

            // Clear entity manager between clients
            $this->entityManager->clear();

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $io->success([
            'Client product prices creation completed',
            sprintf('Total prices created: %d across %d clients', $totalPrices, count($clients))
        ]);

        return Command::SUCCESS;
    }

    /**
     * Create prices for a single client
     */
    private function createPricesForClient(Client $client, int $clientIndex, int $totalProducts, int $batchSize, bool $skipExisting, SymfonyStyle $io): int
    {
        // Define price range for this client
        $priceRangeMin = self::MIN_PRICE + ($clientIndex * 100);
        $priceRangeMax = min(self::MAX_PRICE, $priceRangeMin + 300);

        $io->comment(sprintf('Setting price range: %d-%d', $priceRangeMin, $priceRangeMax));

        // Create progress bar
        $progressBar = new ProgressBar($io, $totalProducts);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        $progressBar->start();

        $pricesCreated = 0;
        $pricesSkipped = 0;
        $offset = 0;

        while (true) {
            // Get a batch of products
            $products = $this->entityManager->getRepository(Product::class)
                ->createQueryBuilder('p')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                // Check if price already exists for this client-product combination
                if ($skipExisting) {
                    $existingPrice = $this->entityManager->getRepository(ClientProductPrice::class)
                        ->findOneBy([
                            'client' => $client,
                            'product' => $product
                        ]);

                    if ($existingPrice) {
                        $pricesSkipped++;
                        $progressBar->advance();
                        continue;
                    }
                }

                // Generate a price based on product part number to ensure consistency
                $productHash = crc32($product->getPartNo() ?? $product->getId()->__toString());
                $percentile = ($productHash % 100) / 100; // 0.00 to 0.99

                // Calculate price within the client's range
                $price = $priceRangeMin + ($percentile * ($priceRangeMax - $priceRangeMin));
                $price = max(self::MIN_PRICE, min(self::MAX_PRICE, round($price, 2)));

                // Create the custom price entity
                $customPrice = new ClientProductPrice();
                $customPrice
                    ->setClient($client)
                    ->setProduct($product)
                    ->setPrice($price);

                // Add a discount for some products (about 25%)
                if ($productHash % 4 === 0) {
                    $customPrice->setDiscountPercentage(10.0); // 10% discount
                }

                // Add validity period for some products (about 20%)
                if ($productHash % 5 === 0) {
                    $validFrom = new \DateTime();
                    $validUntil = (new \DateTime())->modify('+3 months');

                    $customPrice
                        ->setValidFrom($validFrom)
                        ->setValidUntil($validUntil);
                }

                $this->entityManager->persist($customPrice);
                $pricesCreated++;

                // Advance progress bar
                $progressBar->advance();
            }

            // Move to next batch
            $offset += count($products);

            // Flush entities for this batch
            $this->entityManager->flush();

            // Clear entity manager except for the client
            if ($offset % ($batchSize * 5) === 0) {
                $clientId = $client->getId()->__toString();

                $this->entityManager->clear();

                // Reload client entity
                $client = $this->entityManager->getRepository(Client::class)->find($clientId);
            }
        }

        $progressBar->finish();
        $io->newLine();

        if ($pricesSkipped > 0) {
            $io->comment(sprintf('Skipped %d existing prices', $pricesSkipped));
        }

        return $pricesCreated;
    }

    /**
     * Clear all existing client product prices
     */
    private function clearAllPrices(SymfonyStyle $io): void
    {
        try {
            $conn = $this->entityManager->getConnection();
            $platform = $conn->getDatabasePlatform();

            // Disable foreign key checks if needed (for MySQL)
            if (method_exists($platform, 'supportsForeignKeyConstraints') && $platform->supportsForeignKeyConstraints()) {
                $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            }

            // Delete all client product prices with native SQL for better performance
            $deletedRows = $conn->executeStatement('DELETE FROM client_product_price');

            // Re-enable foreign key checks
            if (method_exists($platform, 'supportsForeignKeyConstraints') && $platform->supportsForeignKeyConstraints()) {
                $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            }

            $io->comment(sprintf('Deleted %d existing client product prices', $deletedRows));

        } catch (\Exception $e) {
            $io->warning([
                'Failed to clear existing prices:',
                $e->getMessage()
            ]);
        }
    }

    /**
     * Clear existing prices for a specific client
     */
    private function clearPricesForClient(string $clientCode, SymfonyStyle $io): void
    {
        try {
            $client = $this->entityManager->getRepository(Client::class)
                ->findOneBy(['code' => $clientCode]);

            if (!$client) {
                $io->warning(sprintf('Client with code "%s" not found', $clientCode));
                return;
            }

            $conn = $this->entityManager->getConnection();

            // Delete prices for this client with native SQL
            $deletedRows = $conn->executeStatement(
                'DELETE FROM client_product_price WHERE client_id = :clientId',
                ['clientId' => $client->getId()->__toString()]
            );

            $io->comment(sprintf('Deleted %d existing prices for client %s', $deletedRows, $clientCode));

        } catch (\Exception $e) {
            $io->warning([
                sprintf('Failed to clear existing prices for client %s:', $clientCode),
                $e->getMessage()
            ]);
        }
    }
}
