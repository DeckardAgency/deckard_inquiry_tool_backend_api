<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Documentation;
use App\Entity\DocumentationRevision;
use App\Repository\DocumentationRevisionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DocumentationRevisionProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private DocumentationRevisionRepository $revisionRepository,
        private Security $security,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param Documentation|object $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Documentation) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $method = $operation->getMethod();
        $isUpdate = in_array($method, ['PUT', 'PATCH'], true);

        // For updates, check if content or title has changed and create a revision
        if ($isUpdate && $data->getId()) {
            $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);

            if ($originalData) {
                $contentChanged = ($originalData['content'] ?? '') !== $data->getContent();
                $titleChanged = ($originalData['title'] ?? '') !== $data->getTitle();

                if ($contentChanged || $titleChanged) {
                    $this->logger->info('Documentation content changed, creating revision', [
                        'documentation_id' => $data->getId()->toRfc4122(),
                        'content_changed' => $contentChanged,
                        'title_changed' => $titleChanged
                    ]);

                    // Create revision with the OLD content (before the change)
                    $this->createRevision(
                        $data,
                        $originalData['title'] ?? '',
                        $originalData['content'] ?? '',
                        $context['change_note'] ?? null
                    );
                }
            }
        }

        // For new documentation, create an initial revision after persist
        $isNew = $data->getId() === null;

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Create initial revision for new documentation
        if ($isNew && $result instanceof Documentation) {
            $this->createRevision(
                $result,
                $result->getTitle(),
                $result->getContent(),
                'Initial version'
            );
            $this->entityManager->flush();
        }

        return $result;
    }

    private function createRevision(
        Documentation $documentation,
        string $title,
        string $content,
        ?string $changeNote = null
    ): void {
        $revision = new DocumentationRevision();
        $revision->setDocumentation($documentation);
        $revision->setTitle($title);
        $revision->setContent($content);
        $revision->setEditedAt(new \DateTime());

        // Set the user who made the change
        $user = $this->security->getUser();
        if ($user) {
            $revision->setEditedBy($user);
        }

        // Set change note
        $revision->setChangeNote($changeNote);

        // Get the next revision number
        $latestNumber = $this->revisionRepository->getLatestRevisionNumber($documentation);
        $revision->setRevisionNumber($latestNumber + 1);

        $documentation->addRevision($revision);
        $this->entityManager->persist($revision);
    }
}
