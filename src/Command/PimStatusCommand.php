<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ProductRepository;
use App\Service\Pim\PimApiClient;
use App\Service\Pim\PimFeatureManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pim:status',
    description: 'Show PIM integration status and current sync state'
)]
class PimStatusCommand extends Command
{
    public function __construct(
        private readonly PimFeatureManager $featureManager,
        private readonly PimApiClient $pimClient,
        private readonly ProductRepository $productRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('PIM Status');
        $io->definitionList(
            ['PIM Enabled' => $this->featureManager->isPimEnabled() ? 'Yes' : 'No'],
            ['API URL' => $this->featureManager->getPimApiUrl() ?? '(unset)'],
            ['Channel' => $this->featureManager->getPimChannel() ?? '(unset)'],
            ['API Key' => $this->featureManager->getPimApiKey() ? 'configured' : '(unset)'],
        );

        $io->section('Local product counts');
        $totalPim = (int) $this->productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isFromPim = true')
            ->getQuery()->getSingleScalarResult();
        $totalLocal = (int) $this->productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isFromPim = false OR p.isFromPim IS NULL')
            ->getQuery()->getSingleScalarResult();
        $io->table(
            ['Source', 'Count'],
            [
                ['Products from PIM', $totalPim],
                ['Local products', $totalLocal],
            ]
        );

        if (!$this->featureManager->isPimEnabled()) {
            $io->note('PIM integration is disabled — set PIM_ENABLED=true to sync.');
            return Command::SUCCESS;
        }

        $io->section('PIM connectivity');
        try {
            $sample = $this->pimClient->getProducts([], 1, 1);
            $io->success(sprintf('PIM reachable. Sample fetch returned %d items.', count($sample)));
        } catch (\Throwable $e) {
            $io->error('PIM unreachable: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
