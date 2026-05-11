<?php

namespace App\Security\Voter;

use App\Entity\Inquiry;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class InquiryVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Inquiry;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // If the user is not logged in, deny access
        if (!$user instanceof User) {
            return false;
        }

        /** @var Inquiry $inquiry */
        $inquiry = $subject;

        // Admin users can do anything
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // ROLE_CLIENT_ADMIN can access same-client inquiries
        if (in_array('ROLE_CLIENT_ADMIN', $user->getRoles())
            && $user->getClient()
            && $inquiry->getUser()
            && $inquiry->getUser()->getClient()
            && $user->getClient()->getId()->equals($inquiry->getUser()->getClient()->getId())
        ) {
            return match($attribute) {
                self::VIEW => true,
                self::EDIT => $inquiry->getStatus() === Inquiry::STATUS_PENDING_APPROVAL,
                self::DELETE => $inquiry->isDraft() || $inquiry->getStatus() === Inquiry::STATUS_PENDING_APPROVAL,
                default => false,
            };
        }

        // Check if the inquiry belongs to the current user
        return match($attribute) {
            self::VIEW => $this->canView($inquiry, $user),
            self::EDIT => $this->canEdit($inquiry, $user),
            self::DELETE => $this->canDelete($inquiry, $user),
            default => false,
        };
    }

    private function canView(Inquiry $inquiry, User $user): bool
    {
        // Users can view their own inquiries
        return $inquiry->getUser() === $user;
    }

    private function canEdit(Inquiry $inquiry, User $user): bool
    {
        // Users can edit their own inquiries if they are still drafts
        return $inquiry->getUser() === $user && $inquiry->isDraft();
    }

    private function canDelete(Inquiry $inquiry, User $user): bool
    {
        // Users can delete their own inquiries if they are still drafts
        return $inquiry->getUser() === $user && $inquiry->isDraft();
    }
}
