<?php

namespace App\Controller;

use App\Entity\Documentation;
use App\Entity\DocumentationRevision;
use App\Repository\DocumentationRepository;
use App\Repository\DocumentationRevisionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1')]
class DocumentationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentationRepository $documentationRepository,
        private DocumentationRevisionRepository $revisionRepository
    ) {
    }

    #[Route('/documentations/{id}/restore/{revisionId}', name: 'api_documentation_restore', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function restoreRevision(string $id, string $revisionId): JsonResponse
    {
        try {
            $docId = Uuid::fromString($id);
            $revId = Uuid::fromString($revisionId);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid ID format'], Response::HTTP_BAD_REQUEST);
        }

        $documentation = $this->documentationRepository->find($docId);
        if (!$documentation) {
            return $this->json(['error' => 'Documentation not found'], Response::HTTP_NOT_FOUND);
        }

        $revision = $this->revisionRepository->find($revId);
        if (!$revision) {
            return $this->json(['error' => 'Revision not found'], Response::HTTP_NOT_FOUND);
        }

        // Verify the revision belongs to this documentation
        if ($revision->getDocumentation()->getId()->toRfc4122() !== $documentation->getId()->toRfc4122()) {
            return $this->json(['error' => 'Revision does not belong to this documentation'], Response::HTTP_BAD_REQUEST);
        }

        // Create a new revision to save the current state before restoring
        $this->createRevision(
            $documentation,
            sprintf('Before restoring to revision %d', $revision->getRevisionNumber())
        );

        // Restore the content from the revision
        $documentation->setTitle($revision->getTitle());
        $documentation->setContent($revision->getContent());

        // Create another revision to record the restore action
        $this->createRevision(
            $documentation,
            sprintf('Restored from revision %d', $revision->getRevisionNumber())
        );

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Documentation restored successfully',
            'documentation' => [
                'id' => $documentation->getId()->toRfc4122(),
                'title' => $documentation->getTitle(),
                'slug' => $documentation->getSlug(),
                'restoredFromRevision' => $revision->getRevisionNumber()
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/documentations/categories', name: 'api_documentation_categories', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        $categories = $this->documentationRepository->findAllCategories();

        return $this->json([
            'categories' => $categories
        ], Response::HTTP_OK);
    }

    private function createRevision(Documentation $documentation, string $changeNote): void
    {
        $revision = new DocumentationRevision();
        $revision->setDocumentation($documentation);
        $revision->setTitle($documentation->getTitle());
        $revision->setContent($documentation->getContent());
        $revision->setEditedAt(new \DateTime());
        $revision->setChangeNote($changeNote);

        // Set the user who made the change
        $user = $this->getUser();
        if ($user) {
            $revision->setEditedBy($user);
        }

        // Get the next revision number
        $latestNumber = $this->revisionRepository->getLatestRevisionNumber($documentation);
        $revision->setRevisionNumber($latestNumber + 1);

        $documentation->addRevision($revision);
        $this->entityManager->persist($revision);
    }
}
