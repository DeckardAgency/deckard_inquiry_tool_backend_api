<?php

namespace App\Controller;

use App\State\Provider\InvitationVerifyProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class InvitationVerifyController extends AbstractController
{
    public function __construct(
        private readonly InvitationVerifyProvider $provider
    ) {
    }

    public function __invoke(string $token): JsonResponse
    {
        $output = $this->provider->provide(null, ['token' => $token], []);

        return $this->json([
            'email' => $output->email,
            'firstName' => $output->firstName,
            'lastName' => $output->lastName,
            'expiresAt' => $output->expiresAt->format('c'),
            'isExpired' => $output->isExpired,
        ]);
    }
}
