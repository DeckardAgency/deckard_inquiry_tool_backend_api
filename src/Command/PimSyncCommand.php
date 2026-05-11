<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Pim\ProductSyncService;
use App\Service\Pim\PimFeatureManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pim:sync',
    description: 'Sync products from the Deckard PIM into this app'
)]
class PimSyncCommand extends Command
{
    public function __construct(
        private readonly ProductSyncService $syncService,
        private readonly PimFeatureManager $featureManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('full', 'f', InputOption::VALUE_NONE, 'Force full sync (all products)')
            ->addOption('since', 's', InputOption::VALUE_REQUIRED, 'ISO 8601 datetime or "-1 hour" style for incremental')
            ->setHelp(<<<'HELP'
Full sync (all products on the active channel):
    php %command.full_name% --full

Incremental sync (default - last 24 hours):
    php %command.full_name%

Since a specific time:
    php %command.full_name% --since="-1 hour"
    php %command.full_name% --since="2026-05-01 10:00:00"
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->featureManager->isPimEnabled()) {
            $io->error('PIM integration is disabled. Set PIM_ENABLED=true.');
            return Command::FAILURE;
        }

        $io->title('PIM Product Synchronization');
        $io->definitionList(
            ['API URL' => $this->featureManager->getPimApiUrl()],
            ['Channel' => $this->featureManager->getPimChannel()],
            ['Batch Size' => (string) $this->featureManager->getSyncBatchSize()],
        );

        $started = microtime(true);
        if ($input->getOption('full')) {
            $io->section('Running full sync...');
            $result = $this->syncService->fullSync();
        } else {
            $sinceRaw = $input->getOption('since') ?? '-24 hours';
            $since = new \DateTimeImmutable($sinceRaw);
            $io->section('Running incremental sync since ' . $since->format('c'));
            $result = $this->syncService->incrementalSync($since);
        }
        $duration = round(microtime(true) - $started, 2);

        $io->table(
            ['Metric', 'Count'],
            [
                ['Created', $result->getCreated()],
                ['Updated', $result->getUpdated()],
                ['Skipped (unchanged)', $result->getSkipped()],
                ['Deleted (orphans)', $result->getDeleted()],
                ['Total processed', $result->getTotal()],
            ]
        );

        if ($result->hasErrors()) {
            $io->warning(sprintf('Sync finished with %d errors in %ss', count($result->getErrors()), $duration));
            foreach (array_slice($result->getErrors(), 0, 10) as $err) {
                $io->writeln('  - ' . $err);
            }
            return Command::FAILURE;
        }

        $io->success("Sync completed successfully in {$duration}s");
        return Command::SUCCESS;
    }
}
