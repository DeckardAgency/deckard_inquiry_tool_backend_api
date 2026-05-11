<?php

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrderVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // If the user is not logged in, deny access
        if (!$user instanceof User) {
            return false;
        }

        /** @var Order $order */
        $order = $subject;

        // Admin users can do anything
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // ROLE_CLIENT_ADMIN can access same-client orders
        if (in_array('ROLE_CLIENT_ADMIN', $user->getRoles())
            && $user->getClient()
            && $order->getUser()
            && $order->getUser()->getClient()
            && $user->getClient()->getId()->equals($order->getUser()->getClient()->getId())
        ) {
            return match($attribute) {
                self::VIEW => true,
                self::EDIT => $order->getStatus() === Order::STATUS_PENDING_APPROVAL,
                self::DELETE => $order->isDraft() || $order->getStatus() === Order::STATUS_PENDING_APPROVAL,
                default => false,
            };
        }

        // Check if the order belongs to the current user
        return match($attribute) {
            self::VIEW => $this->canView($order, $user),
            self::EDIT => $this->canEdit($order, $user),
            self::DELETE => $this->canDelete($order, $user),
            default => false,
        };
    }

    private function canView(Order $order, User $user): bool
    {
        // Users can view their own orders
        return $order->getUser() === $user;
    }

    private function canEdit(Order $order, User $user): bool
    {
        // Users can edit their own orders if they are still drafts
        return $order->getUser() === $user && $order->isDraft();
    }

    private function canDelete(Order $order, User $user): bool
    {
        // Users can delete their own orders if they are still drafts
        return $order->getUser() === $user && $order->isDraft();
    }
}
