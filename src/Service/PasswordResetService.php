<?php

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetService
{
    // Rate limiting: max requests per user per hour
    private const MAX_REQUESTS_PER_HOUR = 3;

    // Password requirements
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PasswordResetTokenRepository $tokenRepository,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger,
        private string $clientAppUrl,
        private string $mailFromAddress,
        private string $mailFromName
    ) {
    }

    /**
     * Request a password reset for a user (triggered by admin)
     *
     * @throws \Exception if rate limit exceeded or other error
     */
    public function requestPasswordReset(User $user, User $admin, ?string $ipAddress = null): PasswordResetToken
    {
        // Check rate limiting
        $recentCount = $this->tokenRepository->countRecentByUser($user, 1);
        if ($recentCount >= self::MAX_REQUESTS_PER_HOUR) {
            throw new \Exception(sprintf(
                'Rate limit exceeded. Maximum %d password reset requests per hour.',
                self::MAX_REQUESTS_PER_HOUR
            ));
        }

        // Revoke any existing pending tokens for this user
        $this->tokenRepository->revokeAllForUser($user);

        // Create new token
        $token = new PasswordResetToken();
        $token->setUser($user);
        $token->setCreatedBy($admin);
        $token->setIpAddress($ipAddress);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        // Send email
        $this->sendPasswordResetEmail($token);

        $this->logger->info('Password reset requested', [
            'user_id' => $user->getId()->toRfc4122(),
            'user_email' => $user->getEmail(),
            'admin_id' => $admin->getId()->toRfc4122(),
            'admin_email' => $admin->getEmail(),
            'token_id' => $token->getId()->toRfc4122(),
            'expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Request a password reset for a user (self-service by user entering their email)
     *
     * @throws \Exception if rate limit exceeded
     */
    public function requestPasswordResetByEmail(string $email, ?string $ipAddress = null): ?PasswordResetToken
    {
        // Find user by email - we don't reveal if email exists or not for security
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Log the attempt but don't reveal that email doesn't exist
            $this->logger->info('Password reset requested for non-existent email', [
                'email' => $email,
                'ip_address' => $ipAddress,
            ]);
            return null;
        }

        // Check if user is active
        if (!$user->getIsActive()) {
            $this->logger->warning('Password reset requested for inactive user', [
                'email' => $email,
                'ip_address' => $ipAddress,
            ]);
            return null;
        }

        // Check rate limiting
        $recentCount = $this->tokenRepository->countRecentByUser($user, 1);
        if ($recentCount >= self::MAX_REQUESTS_PER_HOUR) {
            throw new \Exception(sprintf(
                'Too many password reset requests. Please try again later.',
                self::MAX_REQUESTS_PER_HOUR
            ));
        }

        // Revoke any existing pending tokens for this user
        $this->tokenRepository->revokeAllForUser($user);

        // Create new token - user is their own "creator" for self-service resets
        $token = new PasswordResetToken();
        $token->setUser($user);
        $token->setCreatedBy($user); // Self-service: user is creator
        $token->setIpAddress($ipAddress);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        // Send email
        $this->sendPasswordResetEmail($token);

        $this->logger->info('Self-service password reset requested', [
            'user_id' => $user->getId()->toRfc4122(),
            'user_email' => $user->getEmail(),
            'token_id' => $token->getId()->toRfc4122(),
            'expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
        ]);

        return $token;
    }

    /**
     * Validate a password reset token
     */
    public function validateToken(string $tokenString): ?PasswordResetToken
    {
        $token = $this->tokenRepository->findValidToken($tokenString);

        if (!$token) {
            $this->logger->warning('Invalid or expired password reset token attempted', [
                'token_prefix' => substr($tokenString, 0, 10) . '...',
            ]);
            return null;
        }

        return $token;
    }

    /**
     * Validate password meets requirements
     *
     * @throws \Exception if password doesn't meet requirements
     */
    public function validatePassword(string $password): void
    {
        $errors = [];

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = sprintf('Password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH);
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if (!empty($errors)) {
            throw new \Exception(implode(' ', $errors));
        }
    }

    /**
     * Reset the password using a valid token
     *
     * @throws \Exception if token is invalid or expired
     */
    public function resetPassword(string $tokenString, string $newPassword): User
    {
        $token = $this->tokenRepository->findValidToken($tokenString);

        if (!$token) {
            throw new \Exception('Invalid or expired password reset token.');
        }

        // Validate password requirements
        $this->validatePassword($newPassword);

        $user = $token->getUser();
        $admin = $token->getCreatedBy();

        // Hash and set the new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        // Mark token as used
        $token->markAsUsed();

        $this->entityManager->flush();

        $this->logger->info('Password reset completed', [
            'user_id' => $user->getId()->toRfc4122(),
            'user_email' => $user->getEmail(),
            'token_id' => $token->getId()->toRfc4122(),
        ]);

        // Send notification to admin who initiated the reset
        if ($admin) {
            $this->sendAdminNotificationEmail($admin, $user);
        }

        return $user;
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(PasswordResetToken $token): void
    {
        $user = $token->getUser();
        $resetUrl = sprintf('%s/reset-password?token=%s', $this->clientAppUrl, $token->getToken());

        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->mailFromName, $this->mailFromAddress))
            ->to($user->getEmail())
            ->subject('Password Reset Request - Deckard Inquiry Tool')
            ->htmlTemplate('emails/user/password_reset.html.twig')
            ->context([
                'user' => $user,
                'token' => $token,
                'resetUrl' => $resetUrl,
                'expirationHours' => PasswordResetToken::DEFAULT_EXPIRATION_HOURS,
            ]);

        $this->mailer->send($email);

        $this->logger->info('Password reset email sent', [
            'user_id' => $user->getId()->toRfc4122(),
            'user_email' => $user->getEmail(),
        ]);
    }

    /**
     * Send notification email to admin when user completes password reset
     */
    private function sendAdminNotificationEmail(User $admin, User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->mailFromName, $this->mailFromAddress))
            ->to($admin->getEmail())
            ->subject('Password Reset Completed - Deckard Inquiry Tool')
            ->htmlTemplate('emails/user/password_reset_completed.html.twig')
            ->context([
                'admin' => $admin,
                'user' => $user,
                'completedAt' => new \DateTime(),
            ]);

        $this->mailer->send($email);

        $this->logger->info('Admin notification email sent for password reset completion', [
            'admin_id' => $admin->getId()->toRfc4122(),
            'admin_email' => $admin->getEmail(),
            'user_id' => $user->getId()->toRfc4122(),
            'user_email' => $user->getEmail(),
        ]);
    }

    /**
     * Get token info for validation endpoint (without exposing sensitive data)
     */
    public function getTokenInfo(PasswordResetToken $token): array
    {
        return [
            'valid' => $token->canBeUsed(),
            'email' => $token->getPartialEmail(),
            'expiresAt' => $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Cleanup expired tokens
     */
    public function cleanupExpiredTokens(): int
    {
        return $this->tokenRepository->markExpiredTokens();
    }

    /**
     * Delete old tokens
     */
    public function deleteOldTokens(int $daysOld = 30): int
    {
        return $this->tokenRepository->deleteOldTokens($daysOld);
    }
}
