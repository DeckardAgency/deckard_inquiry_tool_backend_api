<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\UserInvitation;
use App\Repository\UserInvitationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvitationVerifyControllerTest extends WebTestCase
{
    private $client;
    private ?UserInvitationRepository $userInvitationRepository = null;
    private ?UserRepository $userRepository = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function getInvitationRepository(): UserInvitationRepository
    {
        if (!$this->userInvitationRepository) {
            $this->userInvitationRepository = static::getContainer()->get(UserInvitationRepository::class);
        }
        return $this->userInvitationRepository;
    }

    private function getUserRepository(): UserRepository
    {
        if (!$this->userRepository) {
            $this->userRepository = static::getContainer()->get(UserRepository::class);
        }
        return $this->userRepository;
    }

    public function testVerifyWithValidToken(): void
    {
        // Create a test invitation
        $invitation = $this->createTestInvitation();
        $token = $invitation->getToken();

        // Make request to verify endpoint
        $this->client->request('GET', "/api/v1/user_invitations/verify/{$token}");

        // Assert response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('email', $responseData);
        $this->assertArrayHasKey('firstName', $responseData);
        $this->assertArrayHasKey('lastName', $responseData);
        $this->assertArrayHasKey('expiresAt', $responseData);
        $this->assertArrayHasKey('isExpired', $responseData);

        $this->assertEquals('test@example.com', $responseData['email']);
        $this->assertEquals('Test', $responseData['firstName']);
        $this->assertEquals('User', $responseData['lastName']);
        $this->assertFalse($responseData['isExpired']);
    }

    public function testVerifyWithInvalidToken(): void
    {
        $invalidToken = 'invalid_token_that_does_not_exist_in_database';

        $this->client->request('GET', "/api/v1/user_invitations/verify/{$invalidToken}");

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        // The exception message might be in different formats depending on Symfony configuration
        $this->assertTrue(
            str_contains($responseContent, 'Invalid invitation token') ||
            isset($responseData['detail']) && str_contains($responseData['detail'], 'Invalid invitation token'),
            'Response should contain "Invalid invitation token"'
        );
    }

    public function testVerifyWithExpiredToken(): void
    {
        // Create an expired invitation
        $invitation = $this->createExpiredInvitation();
        $token = $invitation->getToken();

        $this->client->request('GET', "/api/v1/user_invitations/verify/{$token}");

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('expired@example.com', $responseData['email']);
        $this->assertTrue($responseData['isExpired'], 'Expired invitation should have isExpired = true');
    }

    public function testVerifyWithCompletedInvitation(): void
    {
        // Create a completed invitation
        $invitation = $this->createCompletedInvitation();
        $token = $invitation->getToken();

        $this->client->request('GET', "/api/v1/user_invitations/verify/{$token}");

        // Verify endpoint should still return data for completed invitations
        // The completion check happens in the complete endpoint
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('completed@example.com', $responseData['email']);
    }

    public function testVerifyWithRevokedInvitation(): void
    {
        // Create a revoked invitation
        $invitation = $this->createRevokedInvitation();
        $token = $invitation->getToken();

        $this->client->request('GET', "/api/v1/user_invitations/verify/{$token}");

        // Verify endpoint should still return data for revoked invitations
        // The revoked check happens in the complete endpoint
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('revoked@example.com', $responseData['email']);
    }

    public function testVerifyResponseStructure(): void
    {
        $invitation = $this->createTestInvitation();
        $token = $invitation->getToken();

        $this->client->request('GET', "/api/v1/user_invitations/verify/{$token}");

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        // Assert all required fields are present
        $requiredFields = ['email', 'firstName', 'lastName', 'expiresAt', 'isExpired'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $responseData, "Response must contain '{$field}' field");
        }

        // Assert field types
        $this->assertIsString($responseData['email']);
        $this->assertIsString($responseData['firstName']);
        $this->assertIsString($responseData['lastName']);
        $this->assertIsString($responseData['expiresAt']);
        $this->assertIsBool($responseData['isExpired']);

        // Assert expiresAt is a valid ISO 8601 date
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $responseData['expiresAt'],
            'expiresAt must be in ISO 8601 format'
        );
    }

    // Helper methods

    private function createTestInvitation(): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create a test user to be the creator
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail('test@example.com');
        $invitation->setFirstName('Test');
        $invitation->setLastName('User');
        $invitation->generateToken();
        $invitation->setDefaultExpiration(7);
        $invitation->setStatus(UserInvitation::STATUS_PENDING);
        $invitation->setCreatedBy($createdBy);

        $entityManager->persist($invitation);
        $entityManager->flush();

        return $invitation;
    }

    private function getOrCreateTestUser($entityManager): User
    {
        $userRepository = $this->getUserRepository();
        $user = $userRepository->findOneBy(['email' => 'test-creator@example.com']);

        if (!$user) {
            $user = new User();
            $user->setEmail('test-creator@example.com');
            $user->setFirstName('Test');
            $user->setLastName('Creator');
            $user->setRoles(['ROLE_ADMIN']);

            // Set a dummy password
            $passwordHasher = static::getContainer()->get('security.user_password_hasher');
            $user->setPassword($passwordHasher->hashPassword($user, 'TestPassword123'));

            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $user;
    }

    private function createExpiredInvitation(): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail('expired@example.com');
        $invitation->setFirstName('Expired');
        $invitation->setLastName('User');
        $invitation->generateToken();
        $invitation->setStatus(UserInvitation::STATUS_PENDING);
        $invitation->setCreatedBy($createdBy);

        // Set expiration date to yesterday
        $yesterday = new \DateTimeImmutable('-1 day');
        $invitation->setExpiresAt($yesterday);

        $entityManager->persist($invitation);
        $entityManager->flush();

        return $invitation;
    }

    private function createCompletedInvitation(): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail('completed@example.com');
        $invitation->setFirstName('Completed');
        $invitation->setLastName('User');
        $invitation->generateToken();
        $invitation->setDefaultExpiration(7);
        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);
        $invitation->setCompletedAt(new \DateTimeImmutable());
        $invitation->setCreatedBy($createdBy);

        $entityManager->persist($invitation);
        $entityManager->flush();

        return $invitation;
    }

    private function createRevokedInvitation(): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail('revoked@example.com');
        $invitation->setFirstName('Revoked');
        $invitation->setLastName('User');
        $invitation->generateToken();
        $invitation->setDefaultExpiration(7);
        $invitation->setStatus(UserInvitation::STATUS_REVOKED);
        $invitation->setCreatedBy($createdBy);

        $entityManager->persist($invitation);
        $entityManager->flush();

        return $invitation;
    }

    protected function tearDown(): void
    {
        // Clean up test data before shutting down kernel
        if (self::$booted) {
            $entityManager = static::getContainer()->get('doctrine')->getManager();
            $connection = $entityManager->getConnection();

            // Delete test invitations
            try {
                $connection->executeStatement(
                    'DELETE FROM user_invitation WHERE email IN (?, ?, ?, ?)',
                    ['test@example.com', 'expired@example.com', 'completed@example.com', 'revoked@example.com']
                );
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }
}
