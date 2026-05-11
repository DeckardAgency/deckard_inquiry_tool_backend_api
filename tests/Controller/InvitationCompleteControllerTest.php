<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\UserInvitation;
use App\Repository\UserInvitationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvitationCompleteControllerTest extends WebTestCase
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

    public function testCompleteInvitationWithValidData(): void
    {
        // Create a test invitation
        $email = 'complete1-' . uniqid() . '@example.com';
        $invitation = $this->createTestInvitation($email);
        $token = $invitation->getToken();

        // Prepare request data
        $requestData = [
            'password' => 'SecureP@ssw0rd123',
            'passwordConfirm' => 'SecureP@ssw0rd123'
        ];

        // Make request to complete endpoint
        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        // Assert response
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Account created successfully. You can now login.', $responseData['message']);

        // Verify user was created
        $user = $this->getUserRepository()->findOneBy(['email' => $email]);
        $this->assertNotNull($user, 'User should be created');
        $this->assertStringStartsWith('Complete1', $user->getFirstName());
        $this->assertEquals('User', $user->getLastName());

        // Verify invitation was marked as completed
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->refresh($invitation);

        $this->assertEquals(UserInvitation::STATUS_COMPLETED, $invitation->getStatus());
        $this->assertNotNull($invitation->getCompletedAt());
    }

    public function testCompleteInvitationWithInvalidToken(): void
    {
        $invalidToken = 'invalid_token_that_does_not_exist';

        $requestData = [
            'password' => 'SecureP@ssw0rd123',
            'passwordConfirm' => 'SecureP@ssw0rd123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$invalidToken}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

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

    public function testCompleteInvitationWithExpiredToken(): void
    {
        $invitation = $this->createExpiredInvitation('expired-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        $requestData = [
            'password' => 'SecureP@ssw0rd123',
            'passwordConfirm' => 'SecureP@ssw0rd123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('expired', strtolower($responseData['detail'] ?? ''));
    }

    public function testCompleteInvitationWithAlreadyCompletedToken(): void
    {
        $invitation = $this->createCompletedInvitation('completed-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        $requestData = [
            'password' => 'SecureP@ssw0rd123',
            'passwordConfirm' => 'SecureP@ssw0rd123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already been used', $responseData['detail'] ?? '');
    }

    public function testCompleteInvitationWithRevokedToken(): void
    {
        $invitation = $this->createRevokedInvitation('revoked-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        $requestData = [
            'password' => 'SecureP@ssw0rd123',
            'passwordConfirm' => 'SecureP@ssw0rd123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('revoked', strtolower($responseData['detail'] ?? ''));
    }

    public function testCompleteInvitationWithWeakPassword(): void
    {
        $invitation = $this->createTestInvitation('weak-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        // Password without uppercase
        $requestData = [
            'password' => 'weakpassword123',
            'passwordConfirm' => 'weakpassword123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        // Should fail validation
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCompleteInvitationWithShortPassword(): void
    {
        $invitation = $this->createTestInvitation('short-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        $requestData = [
            'password' => 'Pass1',
            'passwordConfirm' => 'Pass1'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        // Should fail validation (minimum 8 characters)
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCompleteInvitationWithMismatchedPasswords(): void
    {
        $invitation = $this->createTestInvitation('mismatched-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        $requestData = [
            'password' => 'SecureP@ssw0rd123',
            'passwordConfirm' => 'DifferentP@ssw0rd123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        // Should fail validation
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCompleteInvitationCreatesUserWithCorrectRoles(): void
    {
        $email = 'roles-' . uniqid() . '@example.com';
        $invitation = $this->createTestInvitationWithRoles(
            $email,
            ['ROLE_CLIENT', 'ROLE_CLIENT_ADMIN']
        );
        $token = $invitation->getToken();

        $requestData = [
            'password' => 'SecureP@ssw0rd123',
            'passwordConfirm' => 'SecureP@ssw0rd123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseIsSuccessful();

        // Verify user has correct roles
        $user = $this->getUserRepository()->findOneBy(['email' => $email]);
        $this->assertNotNull($user);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_CLIENT', $roles);
        $this->assertContains('ROLE_CLIENT_ADMIN', $roles);
    }

    public function testCompleteInvitationPasswordIsHashed(): void
    {
        $email = 'hashed-' . uniqid() . '@example.com';
        $invitation = $this->createTestInvitation($email);
        $token = $invitation->getToken();

        $plainPassword = 'SecureP@ssw0rd123';
        $requestData = [
            'password' => $plainPassword,
            'passwordConfirm' => $plainPassword
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseIsSuccessful();

        // Verify password is hashed
        $user = $this->getUserRepository()->findOneBy(['email' => $email]);
        $this->assertNotNull($user);

        $hashedPassword = $user->getPassword();
        $this->assertNotEquals($plainPassword, $hashedPassword, 'Password should be hashed');
        $this->assertStringStartsWith('$2y$', $hashedPassword, 'Password should be bcrypt hashed');

        // Verify password can be verified
        $passwordHasher = static::getContainer()->get('security.user_password_hasher');
        $this->assertTrue(
            $passwordHasher->isPasswordValid($user, $plainPassword),
            'Hashed password should be verifiable'
        );
    }

    public function testCompleteInvitationWithMissingPassword(): void
    {
        $invitation = $this->createTestInvitation('missing-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        $requestData = [
            'passwordConfirm' => 'SecureP@ssw0rd123'
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCompleteInvitationWithEmptyPassword(): void
    {
        $invitation = $this->createTestInvitation('empty-' . uniqid() . '@example.com');
        $token = $invitation->getToken();

        $requestData = [
            'password' => '',
            'passwordConfirm' => ''
        ];

        $this->client->request(
            'POST',
            "/api/v1/user_invitations/complete/{$token}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // Helper methods

    private function getOrCreateTestUser($entityManager): User
    {
        $user = new User();
        $user->setEmail('test-creator-' . uniqid() . '@example.com');
        $user->setFirstName('Test');
        $user->setLastName('Creator');
        $user->setRoles(['ROLE_ADMIN']);

        // Set a dummy password
        $passwordHasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($passwordHasher->hashPassword($user, 'TestPassword123'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createTestInvitation(string $email): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail($email);
        $invitation->setFirstName(ucfirst(explode('@', $email)[0]));
        $invitation->setLastName('User');
        $invitation->generateToken();
        $invitation->setDefaultExpiration(7);
        $invitation->setStatus(UserInvitation::STATUS_PENDING);
        $invitation->setRoles(['ROLE_CLIENT']);
        $invitation->setCreatedBy($createdBy);

        $entityManager->persist($invitation);
        $entityManager->flush();

        return $invitation;
    }

    private function createTestInvitationWithRoles(string $email, array $roles): UserInvitation
    {
        $invitation = $this->createTestInvitation($email);
        $invitation->setRoles($roles);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->flush();

        return $invitation;
    }

    private function createExpiredInvitation(string $email): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail($email);
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

    private function createCompletedInvitation(string $email): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail($email);
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

    private function createRevokedInvitation(string $email): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $createdBy = $this->getOrCreateTestUser($entityManager);

        $invitation = new UserInvitation();
        $invitation->setEmail($email);
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

            // Delete test users and invitations
            try {
                $connection->executeStatement(
                    "DELETE FROM user WHERE email LIKE '%@example.com'"
                );
                $connection->executeStatement(
                    "DELETE FROM user_invitation WHERE email LIKE '%@example.com'"
                );
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }
}
