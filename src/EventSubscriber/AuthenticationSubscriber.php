<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\LoginAttemptService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class AuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
        private UserRepository $userRepository,
        private RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Reset failed login attempts on successful login
        $this->loginAttemptService->recordSuccessfulLogin($user);
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        // Try to get the username from the request
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $content = json_decode($request->getContent(), true);
        $username = $content['username'] ?? null;

        if (!$username) {
            return;
        }

        // Find the user by email
        $user = $this->userRepository->findOneBy(['email' => $username]);

        if (!$user) {
            return;
        }

        // Check if account is already locked
        $lockStatus = $this->loginAttemptService->checkAccountLock($user);
        if ($lockStatus['locked']) {
            $event->setResponse(new JsonResponse([
                'code' => 423,
                'message' => $lockStatus['message'],
                'locked' => true,
                'remainingMinutes' => $lockStatus['remainingMinutes'],
            ], 423));
            return;
        }

        // Record the failed attempt
        $result = $this->loginAttemptService->recordFailedAttempt($user);

        if ($result['locked']) {
            $event->setResponse(new JsonResponse([
                'code' => 423,
                'message' => $result['message'],
                'locked' => true,
            ], 423));
        } else {
            // Modify the response to include remaining attempts info
            $event->setResponse(new JsonResponse([
                'code' => 401,
                'message' => $result['message'],
                'locked' => false,
                'remainingAttempts' => $result['remainingAttempts'],
            ], 401));
        }
    }
}
