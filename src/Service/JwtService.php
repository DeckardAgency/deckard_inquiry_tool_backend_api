<?php

namespace App\Service;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class JwtService
{
    private JWTTokenManagerInterface $jwtManager;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        TokenStorageInterface $tokenStorage
    ) {
        $this->jwtManager = $jwtManager;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Get a JWT token for a user
     */
    public function createJwtToken(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * Get a current authenticated user
     */
    public function getCurrentUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

    /**
     * Get JWT payload data
     */
    public function getTokenData(string $token): array
    {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return [];
        }

        $payload = base64_decode($tokenParts[1]);
        return json_decode($payload, true) ?? [];
    }
}
