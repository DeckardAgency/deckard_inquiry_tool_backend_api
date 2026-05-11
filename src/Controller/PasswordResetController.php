<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly UserRepository $userRepository,
        private readonly PasswordResetTokenRepository $passwordResetTokenRepository,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Admin endpoint to request password reset for a user
     */
    #[Route('/api/admin/users/{id}/request-password-reset', name: 'admin_request_password_reset', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function requestPasswordReset(string $id, Request $request): JsonResponse
    {
        // Get the admin user
        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Find the target user
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user is active
        if (!$user->getIsActive()) {
            return $this->json(['error' => 'Cannot reset password for inactive user'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $token = $this->passwordResetService->requestPasswordReset(
                $user,
                $admin,
                $request->getClientIp()
            );

            return $this->json([
                'success' => true,
                'message' => sprintf('Password reset email sent to %s', $user->getEmail()),
                'expiresAt' => $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Public endpoint for users to request password reset by email (forgot password)
     */
    #[Route('/api/auth/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || empty($data['email'])) {
            return $this->json([
                'error' => 'Email is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email']);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'error' => 'Please enter a valid email address',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // This will return null if user not found, but we don't reveal that
            $this->passwordResetService->requestPasswordResetByEmail(
                $email,
                $request->getClientIp()
            );

            // Always return success to prevent email enumeration
            return $this->json([
                'success' => true,
                'message' => 'If an account with that email exists, you will receive a password reset link shortly.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            // Rate limit or other error
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
    }

    /**
     * Public endpoint to validate a password reset token
     */
    #[Route('/api/auth/validate-reset-token/{token}', name: 'validate_reset_token', methods: ['GET'])]
    public function validateToken(string $token): JsonResponse
    {
        $resetToken = $this->passwordResetService->validateToken($token);

        if (!$resetToken) {
            return $this->json([
                'valid' => false,
                'error' => 'Invalid or expired token',
            ], Response::HTTP_OK);
        }

        $tokenInfo = $this->passwordResetService->getTokenInfo($resetToken);

        return $this->json($tokenInfo, Response::HTTP_OK);
    }

    /**
     * Admin endpoint to get password reset history for a user
     */
    #[Route('/api/admin/users/{id}/password-reset-history', name: 'admin_get_password_reset_history', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getPasswordResetHistory(string $id): JsonResponse
    {
        // Get the admin user
        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Find the target user
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found', 'id' => $id], Response::HTTP_NOT_FOUND);
        }

        // Get password reset history using native SQL for UUID compatibility
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT
                    HEX(prt.id) as id,
                    prt.status,
                    prt.created_at,
                    prt.expires_at,
                    prt.used_at,
                    prt.ip_address,
                    HEX(prt.created_by_id) as created_by_id,
                    cb.email as created_by_email,
                    cb.first_name as created_by_first_name,
                    cb.last_name as created_by_last_name
                FROM password_reset_token prt
                LEFT JOIN user cb ON prt.created_by_id = cb.id
                WHERE prt.user_id = :userId
                ORDER BY prt.created_at DESC
                LIMIT 20';

        $stmt = $conn->prepare($sql);
        $tokenRows = $stmt->executeQuery(['userId' => $user->getId()->toBinary()])->fetchAllAssociative();

        $history = array_map(function ($row) {
            // Convert HEX ID back to UUID format
            $id = substr($row['id'], 0, 8) . '-' .
                  substr($row['id'], 8, 4) . '-' .
                  substr($row['id'], 12, 4) . '-' .
                  substr($row['id'], 16, 4) . '-' .
                  substr($row['id'], 20);

            $createdById = $row['created_by_id'] ? (
                substr($row['created_by_id'], 0, 8) . '-' .
                substr($row['created_by_id'], 8, 4) . '-' .
                substr($row['created_by_id'], 12, 4) . '-' .
                substr($row['created_by_id'], 16, 4) . '-' .
                substr($row['created_by_id'], 20)
            ) : null;

            return [
                'id' => strtolower($id),
                'status' => $row['status'],
                'createdAt' => $row['created_at'] ? (new \DateTime($row['created_at']))->format(\DateTimeInterface::ATOM) : null,
                'expiresAt' => $row['expires_at'] ? (new \DateTime($row['expires_at']))->format(\DateTimeInterface::ATOM) : null,
                'usedAt' => $row['used_at'] ? (new \DateTime($row['used_at']))->format(\DateTimeInterface::ATOM) : null,
                'ipAddress' => $row['ip_address'],
                'createdBy' => $row['created_by_id'] ? [
                    'id' => strtolower($createdById),
                    'email' => $row['created_by_email'],
                    'firstName' => $row['created_by_first_name'],
                    'lastName' => $row['created_by_last_name'],
                ] : null,
            ];
        }, $tokenRows);

        return $this->json([
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ],
            'history' => $history,
            'totalResets' => count($tokenRows),
        ], Response::HTTP_OK);
    }

    /**
     * Public endpoint to reset password using a valid token
     */
    #[Route('/api/auth/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate input
        if (!isset($data['token']) || !isset($data['newPassword'])) {
            return $this->json([
                'error' => 'Token and newPassword are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $token = $data['token'];
        $newPassword = $data['newPassword'];

        // Validate password strength
        $passwordConstraints = [
            new Assert\NotBlank(['message' => 'Password cannot be blank']),
            new Assert\Length([
                'min' => 8,
                'minMessage' => 'Password must be at least {{ limit }} characters',
                'max' => 100,
                'maxMessage' => 'Password cannot be longer than {{ limit }} characters',
            ]),
        ];

        $violations = $this->validator->validate($newPassword, $passwordConstraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
            return $this->json([
                'error' => 'Password validation failed',
                'details' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->passwordResetService->resetPassword($token, $newPassword);

            return $this->json([
                'success' => true,
                'message' => 'Password has been reset successfully',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
