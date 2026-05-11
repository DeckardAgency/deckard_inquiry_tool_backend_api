<?php

namespace App\Controller;

use App\Entity\Inquiry;
use App\Repository\InquiryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class InquiryDraftController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private InquiryRepository $inquiryRepository;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
        $this->inquiryRepository = $entityManager->getRepository(Inquiry::class);
    }

    #[Route('/api/inquiry-drafts', name: 'api_get_inquiry_drafts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getDrafts(): JsonResponse
    {
        $user = $this->getUser();
        $drafts = $this->inquiryRepository->findDraftsByUser($user);

        // Return drafts (API Platform will handle the serialization)
        return $this->json($drafts, Response::HTTP_OK, [], [
            'groups' => ['inquiry:read', 'inquiry_item:read']
        ]);
    }

    #[Route('/api/inquiries/{id}/save-draft', name: 'api_inquiry_save_draft', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveDraft(string $id, Request $request): JsonResponse
    {
        $user = $this->getUser();

        try {
            $inquiryId = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid inquiry ID format'], Response::HTTP_BAD_REQUEST);
        }

        $inquiry = $this->inquiryRepository->find($inquiryId);

        if (!$inquiry) {
            return $this->json(['error' => 'Inquiry not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the inquiry belongs to the current user
        if ($inquiry->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return $this->json(['error' => 'You do not have permission to modify this inquiry'], Response::HTTP_FORBIDDEN);
        }

        // Save as draft
        $inquiry->saveDraft();
        $this->entityManager->flush();

        return $this->json($inquiry, Response::HTTP_OK, [], [
            'groups' => ['inquiry:read']
        ]);
    }

    #[Route('/api/inquiries/{id}/submit', name: 'api_inquiry_submit_draft', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitDraft(string $id): JsonResponse
    {
        $user = $this->getUser();

        try {
            $inquiryId = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid inquiry ID format'], Response::HTTP_BAD_REQUEST);
        }

        $inquiry = $this->inquiryRepository->find($inquiryId);

        if (!$inquiry) {
            return $this->json(['error' => 'Inquiry not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the inquiry belongs to the current user
        if ($inquiry->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return $this->json(['error' => 'You do not have permission to modify this inquiry'], Response::HTTP_FORBIDDEN);
        }

        // Check if the inquiry can be submitted
        if (!$inquiry->canBeSubmitted()) {
            return $this->json([
                'error' => 'Inquiry cannot be submitted',
                'validation_errors' => $inquiry->getSubmissionErrors()
            ], Response::HTTP_BAD_REQUEST);
        }

        // Submit the draft inquiry
        $inquiry->submitInquiry();
        $this->entityManager->flush();

        return $this->json($inquiry, Response::HTTP_OK, [], [
            'groups' => ['inquiry:read']
        ]);
    }
}
