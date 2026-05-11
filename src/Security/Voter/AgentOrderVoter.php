<?php

namespace App\Security\Voter;

use App\Entity\Client;
use App\Entity\Inquiry;
use App\Entity\Order;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Validates that when onBehalfOfClient is set on an Order or Inquiry,
 * the authenticated user is an agent who manages that client.
 */
class AgentOrderVoter extends Voter
{
    public const AGENT_CREATE = 'AGENT_CREATE';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::AGENT_CREATE
            && ($subject instanceof Order || $subject instanceof Inquiry);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $onBehalfOfClient = $subject->getOnBehalfOfClient();

        // If no onBehalfOfClient is set, this voter doesn't apply — abstain
        if ($onBehalfOfClient === null) {
            return true;
        }

        // User must have ROLE_USER_CLIENT_AGENT
        if (!in_array('ROLE_USER_CLIENT_AGENT', $user->getRoles(), true)) {
            $this->logger->warning('Non-agent user attempted to set onBehalfOfClient', [
                'userId' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
            ]);
            return false;
        }

        $agentClient = $user->getClient();

        // User's company must be a client agent
        if (!$agentClient || !$agentClient->getIsClientAgent()) {
            $this->logger->warning('Agent user company is not configured as client agent', [
                'userId' => $user->getId()->toRfc4122(),
                'clientId' => $agentClient?->getId()?->toRfc4122(),
            ]);
            return false;
        }

        // The target client must be in the agent's managed clients
        if (!$agentClient->managesClient($onBehalfOfClient)) {
            $this->logger->warning('Agent attempted to order for unmanaged client', [
                'userId' => $user->getId()->toRfc4122(),
                'agentClientId' => $agentClient->getId()->toRfc4122(),
                'targetClientId' => $onBehalfOfClient->getId()->toRfc4122(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Static helper to validate agent authorization.
     * Can be called directly from processors without going through the voter system.
     */
    public static function validateAgentAuthorization(User $user, ?Client $onBehalfOfClient): void
    {
        if ($onBehalfOfClient === null) {
            return;
        }

        // Admins can manage any order, skip agent validation
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if (!in_array('ROLE_USER_CLIENT_AGENT', $user->getRoles(), true)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Only users with ROLE_USER_CLIENT_AGENT can place orders on behalf of other clients.'
            );
        }

        $agentClient = $user->getClient();

        if (!$agentClient || !$agentClient->getIsClientAgent()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Your company is not configured as a client agent.'
            );
        }

        if (!$agentClient->managesClient($onBehalfOfClient)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Your company does not manage the specified client.'
            );
        }
    }

    /**
     * Static helper to validate agent authorization for per-item clients.
     * Checks each order item's onBehalfOfClient field.
     */
    public static function validateAgentItemAuthorization(User $user, Order $order): void
    {
        // Admins can manage any order, skip agent validation
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $agentClient = $user->getClient();

        foreach ($order->getItems() as $item) {
            $itemClient = $item->getOnBehalfOfClient();
            if ($itemClient !== null) {
                if (!in_array('ROLE_USER_CLIENT_AGENT', $user->getRoles(), true)) {
                    throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                        'Only agent users can set per-item clients.'
                    );
                }
                if (!$agentClient || !$agentClient->getIsClientAgent()) {
                    throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                        'Your company is not configured as a client agent.'
                    );
                }
                if (!$agentClient->managesClient($itemClient)) {
                    throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                        sprintf('Your company does not manage client "%s".', $itemClient->getName())
                    );
                }
            }
        }
    }
}
