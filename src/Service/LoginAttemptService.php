<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LoginAttemptService
{
    // Lock account after this many failed attempts
    private const MAX_FAILED_ATTEMPTS = 5;

    // Lock duration in minutes
    private const LOCK_DURATION_MINUTES = 30;

    // Reset failed attempts after this many minutes of no failed attempts
    private const RESET_AFTER_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Record a failed login attempt
     *
     * @return array{locked: bool, message: string, remainingAttempts: int}
     */
    public function recordFailedAttempt(User $user): array
    {
        // If the last failed attempt was more than RESET_AFTER_MINUTES ago, reset the counter
        $lastFailed = $user->getLastFailedLoginAt();
        if ($lastFailed !== null) {
            $now = new \DateTime();
            $diff = ($now->getTimestamp() - $lastFailed->getTimestamp()) / 60;

            if ($diff > self::RESET_AFTER_MINUTES) {
                $user->resetFailedLoginAttempts();
            }
        }

        // Increment failed attempts
        $user->incrementFailedLoginAttempts();

        $attempts = $user->getFailedLoginAttempts();
        $remainingAttempts = max(0, self::MAX_FAILED_ATTEMPTS - $attempts);

        // Check if we should lock the account
        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $user->lockAccount(self::LOCK_DURATION_MINUTES);

            $this->logger->warning('Account locked due to too many failed login attempts', [
                'user_id' => $user->getId()->toRfc4122(),
                'user_email' => $user->getEmail(),
                'failed_attempts' => $attempts,
                'locked_until' => $user->getLockedUntil()->format('Y-m-d H:i:s'),
            ]);

            $this->entityManager->flush();

            return [
                'locked' => true,
                'message' => sprintf(
                    'Account locked due to too many failed login attempts. Please try again in %d minutes.',
                    self::LOCK_DURATION_MINUTES
                ),
                'remainingAttempts' => 0,
            ];
        }

        $this->entityManager->flush();

        $this->logger->info('Failed login attempt recorded', [
            'user_id' => $user->getId()->toRfc4122(),
            'user_email' => $user->getEmail(),
            'failed_attempts' => $attempts,
            'remaining_attempts' => $remainingAttempts,
        ]);

        return [
            'locked' => false,
            'message' => sprintf(
                'Invalid credentials. %d attempt(s) remaining before account lock.',
                $remainingAttempts
            ),
            'remainingAttempts' => $remainingAttempts,
        ];
    }

    /**
     * Record a successful login attempt (resets failed attempts)
     */
    public function recordSuccessfulLogin(User $user): void
    {
        if ($user->getFailedLoginAttempts() > 0) {
            $user->resetFailedLoginAttempts();
            $this->entityManager->flush();

            $this->logger->info('Failed login attempts reset after successful login', [
                'user_id' => $user->getId()->toRfc4122(),
                'user_email' => $user->getEmail(),
            ]);
        }
    }

    /**
     * Check if user account is currently locked
     *
     * @return array{locked: bool, message: string, remainingMinutes: int}
     */
    public function checkAccountLock(User $user): array
    {
        if (!$user->isLocked()) {
            return [
                'locked' => false,
                'message' => '',
                'remainingMinutes' => 0,
            ];
        }

        $remainingMinutes = $user->getRemainingLockMinutes();

        return [
            'locked' => true,
            'message' => sprintf(
                'Account is locked. Please try again in %d minute(s).',
                $remainingMinutes
            ),
            'remainingMinutes' => $remainingMinutes,
        ];
    }

    /**
     * Manually unlock a user account (admin action)
     */
    public function unlockAccount(User $user): void
    {
        $user->resetFailedLoginAttempts();
        $this->entityManager->flush();

        $this->logger->info('Account manually unlocked', [
            'user_id' => $user->getId()->toRfc4122(),
            'user_email' => $user->getEmail(),
        ]);
    }

    /**
     * Get account status information
     */
    public function getAccountStatus(User $user): array
    {
        return [
            'isLocked' => $user->isLocked(),
            'failedAttempts' => $user->getFailedLoginAttempts(),
            'lockedUntil' => $user->getLockedUntil()?->format(\DateTimeInterface::ATOM),
            'lastFailedAt' => $user->getLastFailedLoginAt()?->format(\DateTimeInterface::ATOM),
            'remainingLockMinutes' => $user->getRemainingLockMinutes(),
        ];
    }
}
