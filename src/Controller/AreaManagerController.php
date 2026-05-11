<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Inquiry;
use App\Entity\Order;
use App\Service\AreaAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/area-managers')]
class AreaManagerController extends AbstractController
{
    public function __construct(
        private readonly AreaAssignmentService $areaAssignmentService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get available area managers for a client
     */
    #[Route('/available/{clientId}', name: 'api_area_managers_available', methods: ['GET'])]
    public function getAvailableManagers(string $clientId): JsonResponse
    {
        $client = $this->entityManager->getRepository(Client::class)->find(Uuid::fromString($clientId));

        if ($client === null) {
            return $this->json(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        $managers = $this->areaAssignmentService->getAvailableManagers($client);

        $data = array_map(function ($areaManager) {
            return [
                'id' => $areaManager->getId()->toRfc4122(),
                'area' => [
                    'id' => $areaManager->getArea()->getId()->toRfc4122(),
                    'name' => $areaManager->getArea()->getName(),
                    'code' => $areaManager->getArea()->getCode(),
                ],
                'manager' => [
                    'id' => $areaManager->getManager()->getId()->toRfc4122(),
                    'name' => $areaManager->getManager()->getFullName(),
                    'email' => $areaManager->getManager()->getEmail(),
                    'phoneNumber' => $areaManager->getManager()->getPhoneNumber(),
                ],
                'isPrimary' => $areaManager->isPrimary(),
                'currentAssignmentCount' => $areaManager->getCurrentAssignmentCount(),
                'maxCapacity' => $areaManager->getMaxCapacity(),
                'availableCapacity' => $areaManager->getAvailableCapacity(),
                'isAtCapacity' => $areaManager->isAtCapacity(),
            ];
        }, $managers);

        return $this->json($data);
    }

    /**
     * Auto-assign area manager to an inquiry
     */
    #[Route('/assign/inquiry/{inquiryId}', name: 'api_area_managers_assign_inquiry', methods: ['POST'])]
    public function assignToInquiry(string $inquiryId, Request $request): JsonResponse
    {
        $inquiry = $this->entityManager->getRepository(Inquiry::class)->find(Uuid::fromString($inquiryId));

        if ($inquiry === null) {
            return $this->json(['error' => 'Inquiry not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $strategyName = $data['strategy'] ?? 'hybrid';

        $assignment = $this->areaAssignmentService->assignToInquiry(
            $inquiry,
            $strategyName,
            $this->getUser()
        );

        if ($assignment === null) {
            return $this->json([
                'error' => 'No area manager could be assigned',
                'message' => 'Either no matching area was found or no managers are available'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'id' => $assignment->getId()->toRfc4122(),
            'areaManager' => [
                'id' => $assignment->getAreaManager()->getId()->toRfc4122(),
                'area' => [
                    'id' => $assignment->getAreaManager()->getArea()->getId()->toRfc4122(),
                    'name' => $assignment->getAreaManager()->getArea()->getName(),
                    'code' => $assignment->getAreaManager()->getArea()->getCode(),
                ],
                'manager' => [
                    'id' => $assignment->getAreaManager()->getManager()->getId()->toRfc4122(),
                    'name' => $assignment->getAreaManager()->getManager()->getFullName(),
                    'email' => $assignment->getAreaManager()->getManager()->getEmail(),
                ],
            ],
            'assignmentType' => $assignment->getAssignmentType(),
            'assignmentStrategy' => $assignment->getAssignmentStrategy(),
            'assignedAt' => $assignment->getAssignedAt()->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Auto-assign area manager to an order
     */
    #[Route('/assign/order/{orderId}', name: 'api_area_managers_assign_order', methods: ['POST'])]
    public function assignToOrder(string $orderId, Request $request): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find(Uuid::fromString($orderId));

        if ($order === null) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $strategyName = $data['strategy'] ?? 'hybrid';

        $assignment = $this->areaAssignmentService->assignToOrder(
            $order,
            $strategyName,
            $this->getUser()
        );

        if ($assignment === null) {
            return $this->json([
                'error' => 'No area manager could be assigned',
                'message' => 'Either no matching area was found or no managers are available'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'id' => $assignment->getId()->toRfc4122(),
            'areaManager' => [
                'id' => $assignment->getAreaManager()->getId()->toRfc4122(),
                'area' => [
                    'id' => $assignment->getAreaManager()->getArea()->getId()->toRfc4122(),
                    'name' => $assignment->getAreaManager()->getArea()->getName(),
                    'code' => $assignment->getAreaManager()->getArea()->getCode(),
                ],
                'manager' => [
                    'id' => $assignment->getAreaManager()->getManager()->getId()->toRfc4122(),
                    'name' => $assignment->getAreaManager()->getManager()->getFullName(),
                    'email' => $assignment->getAreaManager()->getManager()->getEmail(),
                ],
            ],
            'assignmentType' => $assignment->getAssignmentType(),
            'assignmentStrategy' => $assignment->getAssignmentStrategy(),
            'assignedAt' => $assignment->getAssignedAt()->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }
}
