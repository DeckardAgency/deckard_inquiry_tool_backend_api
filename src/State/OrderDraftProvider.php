<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provider for fetching draft orders for the current user
 */
class OrderDraftProvider implements ProviderInterface
{
    private OrderRepository $orderRepository;
    private Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security
    ) {
        $this->orderRepository = $entityManager->getRepository(Order::class);
        $this->security = $security;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('You must be logged in to access draft orders');
        }

        // Return draft orders for the current user
        return $this->orderRepository->findDraftsByUser($user);
    }
}
