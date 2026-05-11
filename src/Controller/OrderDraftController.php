<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class OrderDraftController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private OrderRepository $orderRepository;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
        $this->orderRepository = $entityManager->getRepository(Order::class);
    }

    #[Route('/api/drafts', name: 'api_get_drafts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getDrafts(): JsonResponse
    {
        $user = $this->getUser();
        $drafts = $this->orderRepository->findDraftsByUser($user);

        // Return drafts (API Platform will handle the serialization)
        return $this->json($drafts, Response::HTTP_OK, [], [
            'groups' => ['order:read', 'order_item:read']
        ]);
    }

    #[Route('/api/orders/{id}/save-draft', name: 'api_save_draft', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveDraft(string $id, Request $request): JsonResponse
    {
        $user = $this->getUser();

        try {
            $orderId = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid order ID format'], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the order belongs to the current user
        if ($order->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return $this->json(['error' => 'You do not have permission to modify this order'], Response::HTTP_FORBIDDEN);
        }

        // Save as draft
        $order->saveDraft();
        $this->entityManager->flush();

        return $this->json($order, Response::HTTP_OK, [], [
            'groups' => ['order:read']
        ]);
    }

    #[Route('/api/orders/{id}/submit', name: 'api_submit_draft', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitDraft(string $id): JsonResponse
    {
        $user = $this->getUser();

        try {
            $orderId = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid order ID format'], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the order belongs to the current user
        if ($order->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return $this->json(['error' => 'You do not have permission to modify this order'], Response::HTTP_FORBIDDEN);
        }

        // Check if the order can be submitted
        if (!$order->canBeSubmitted()) {
            return $this->json([
                'error' => 'Order cannot be submitted',
                'validation_errors' => $order->getSubmissionErrors()
            ], Response::HTTP_BAD_REQUEST);
        }

        // Submit the draft order
        $order->submitOrder();
        $this->entityManager->flush();

        return $this->json($order, Response::HTTP_OK, [], [
            'groups' => ['order:read']
        ]);
    }
}
