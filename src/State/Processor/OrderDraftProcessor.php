<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor for managing draft orders (saving and submitting)
 */
class OrderDraftProcessor implements ProcessorInterface
{
    private OrderRepository $orderRepository;
    private Security $security;
    private EntityManagerInterface $entityManager;
    private ProcessorInterface $orderPriceProcessor;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security               $security,
        ProcessorInterface     $orderPriceProcessor = null,
        LoggerInterface        $logger = null
    )
    {
        $this->orderRepository = $entityManager->getRepository(Order::class);
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->orderPriceProcessor = $orderPriceProcessor;
        $this->logger = $logger;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('You must be logged in to manage draft orders');
        }

        // Extract order ID from URI variables
        $orderId = $uriVariables['id'] ?? null;

        if (!$orderId) {
            throw new NotFoundHttpException('Order ID is required');
        }

        // Find the order
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        // ROLE_ADMIN bypasses all ownership checks
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        if (!$isAdmin) {
            // Check access: owner OR same-client ROLE_CLIENT_ADMIN
            $orderUser = $order->getUser();
            $isOwner = $orderUser && $orderUser->getId()->equals($user->getId());
            $isClientAdmin = in_array('ROLE_CLIENT_ADMIN', $user->getRoles(), true);
            $isSameClient = $user->getClient() && $orderUser && $orderUser->getClient()
                && $user->getClient()->getId()->equals($orderUser->getClient()->getId());

            if (!$isOwner && !($isClientAdmin && $isSameClient)) {
                throw new AccessDeniedHttpException('You do not have permission to modify this order');
            }
        }

        $isClientAdmin = in_array('ROLE_CLIENT_ADMIN', $user->getRoles(), true) || $isAdmin;

        // Determine the operation (save-draft, submit, or approve)
        $path = $operation->getUriTemplate();

        if (str_contains($path, '/save-draft')) {
            // Save as draft
            $order->saveDraft();
            $this->entityManager->flush();

            if ($this->logger) {
                $this->logger->info('Order saved as draft', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'order_number' => $order->getOrderNumber()
                ]);
            }
        } elseif (str_contains($path, '/approve')) {
            // Approve a pending order (ROLE_CLIENT_ADMIN or ROLE_ADMIN only)
            if (!$isClientAdmin) {
                throw new AccessDeniedHttpException('Only client administrators can approve orders');
            }

            if ($order->getStatus() !== Order::STATUS_PENDING_APPROVAL) {
                throw new AccessDeniedHttpException('Only orders pending approval can be approved');
            }

            $order->setStatus(Order::STATUS_SUBMITTED);

            // Delegate to OrderPriceProcessor for persistence + message dispatch
            if ($this->orderPriceProcessor) {
                return $this->orderPriceProcessor->process($order, $operation, $uriVariables, $context);
            } else {
                $this->entityManager->flush();
            }

            if ($this->logger) {
                $this->logger->info('Order approved', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'order_number' => $order->getOrderNumber(),
                    'approved_by' => $user->getEmail()
                ]);
            }
        } elseif (str_contains($path, '/submit')) {
            // For agent orders, check the target client's approval settings
            $approvalClient = $order->getOnBehalfOfClient() ?? $user->getClient();
            $requiresApproval = $approvalClient
                && $approvalClient->getRequiresOrderApproval()
                && !in_array('ROLE_CLIENT_ADMIN', $user->getRoles(), true)
                && !in_array('ROLE_ADMIN', $user->getRoles(), true);

            $targetStatus = $requiresApproval
                ? Order::STATUS_PENDING_APPROVAL
                : Order::STATUS_SUBMITTED;

            // Submit the draft order
            $order->submitOrder($targetStatus);

            // If we have the OrderPriceProcessor, use it to handle the submission
            // This ensures prices are calculated and messages are dispatched properly
            if ($this->orderPriceProcessor) {
                return $this->orderPriceProcessor->process($order, $operation, $uriVariables, $context);
            } else {
                // Fallback: just persist
                $this->entityManager->flush();
            }

            if ($this->logger) {
                $this->logger->info('Draft order submitted', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'order_number' => $order->getOrderNumber(),
                    'new_status' => $order->getStatus()
                ]);
            }
        }

        return $order;
    }
}
