<?php

namespace App\Command;

use App\Entity\Documentation;
use App\Entity\DocumentationRevision;
use App\Repository\DocumentationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:import-documentation',
    description: 'Imports documentation from markdown files into the database'
)]
class ImportDocumentationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentationRepository $documentationRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'Path to the directory containing .md files')
            ->addOption('category', 'c', InputOption::VALUE_OPTIONAL, 'Category to assign to all imported documents', null)
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Update existing documents if they already exist')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate import without database changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        $category = $input->getOption('category');
        $updateExisting = $input->getOption('update');
        $dryRun = $input->getOption('dry-run');

        $io->title('Documentation Import from Markdown Files');

        // Validate directory
        if (!is_dir($directory)) {
            $io->error(sprintf('Directory not found: %s', $directory));
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made to the database');
        }

        // Find all .md files
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.md')->depth(0);

        if (!$finder->hasResults()) {
            $io->warning('No .md files found in the specified directory');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d .md files', $finder->count()));

        // Define sort order based on logical order
        $sortOrderMap = [
            'introduction' => 1,
            'getting-started' => 2,
            'dashboard' => 3,
            'user-guide' => 4,
            'shop-mode' => 5,
            'manual-entry' => 6,
            'managing-inquiries' => 7,
            'machines' => 8,
            'faq' => 99
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        if (!$dryRun) {
            $this->entityManager->getConnection()->beginTransaction();
        }

        try {
            foreach ($finder as $file) {
                $filename = $file->getFilenameWithoutExtension();
                $content = $file->getContents();

                // Extract title from first heading (# Title)
                $title = $this->extractTitle($content, $filename);
                $slug = $this->slugify($filename);

                $io->text(sprintf('Processing: %s -> %s', $file->getFilename(), $title));

                // Check if document exists
                $existing = $this->documentationRepository->findBySlug($slug);

                if ($existing && !$updateExisting) {
                    $io->comment(sprintf('  Skipped (already exists): %s', $slug));
                    $skipped++;
                    continue;
                }

                if ($existing) {
                    // Update existing
                    if (!$dryRun) {
                        // Create revision before updating
                        $this->createRevision($existing, 'Updated via import command');

                        $existing->setTitle($title);
                        $existing->setContent($content);
                        if ($category) {
                            $existing->setCategory($category);
                        }
                        if (isset($sortOrderMap[$filename])) {
                            $existing->setSortOrder($sortOrderMap[$filename]);
                        }
                    }
                    $updated++;
                    $io->comment(sprintf('  Updated: %s', $slug));
                } else {
                    // Create new
                    $documentation = new Documentation();
                    $documentation->setTitle($title);
                    $documentation->setSlug($slug);
                    $documentation->setContent($content);
                    $documentation->setCategory($category);
                    $documentation->setSortOrder($sortOrderMap[$filename] ?? 50);
                    $documentation->setIsPublished(true);

                    if (!$dryRun) {
                        $this->entityManager->persist($documentation);

                        // Create initial revision
                        $this->createRevision($documentation, 'Initial import from markdown file');
                    }
                    $created++;
                    $io->comment(sprintf('  Created: %s', $slug));
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();
            }

            $io->success([
                'Import completed successfully!',
                sprintf('Created: %d', $created),
                sprintf('Updated: %d', $updated),
                sprintf('Skipped: %d', $skipped)
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            if (!$dryRun) {
                $this->entityManager->getConnection()->rollBack();
            }
            $io->error([
                'Import failed!',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    private function extractTitle(string $content, string $fallback): string
    {
        // Try to extract title from first # heading
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        // Fallback to humanized filename
        return ucwords(str_replace(['-', '_'], ' ', $fallback));
    }

    private function slugify(string $string): string
    {
        $string = strtolower($string);
        $string = preg_replace('~[^\pL\d]+~u', '-', $string);
        $string = trim($string, '-');
        $string = preg_replace('~-+~', '-', $string);

        return empty($string) ? 'untitled' : $string;
    }

    private function createRevision(Documentation $documentation, string $changeNote): void
    {
        $revision = new DocumentationRevision();
        $revision->setDocumentation($documentation);
        $revision->setTitle($documentation->getTitle());
        $revision->setContent($documentation->getContent());
        $revision->setChangeNote($changeNote);
        $revision->setEditedAt(new \DateTime());

        // Get the next revision number
        $latestNumber = $this->entityManager
            ->getRepository(DocumentationRevision::class)
            ->getLatestRevisionNumber($documentation);

        $revision->setRevisionNumber($latestNumber + 1);

        $documentation->addRevision($revision);
        $this->entityManager->persist($revision);
    }
}
