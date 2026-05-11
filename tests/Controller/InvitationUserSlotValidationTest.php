<?php

namespace App\Tests\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\UserInvitation;
use App\Repository\ClientRepository;
use App\Repository\UserInvitationRepository;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvitationUserSlotValidationTest extends WebTestCase
{
    private $client;
    private ?UserInvitationRepository $userInvitationRepository = null;
    private ?UserRepository $userRepository = null;
    private ?ClientRepository $clientRepository = null;
    private ?string $authToken = null;

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

    private function getClientRepository(): ClientRepository
    {
        if (!$this->clientRepository) {
            $this->clientRepository = static::getContainer()->get(ClientRepository::class);
        }
        return $this->clientRepository;
    }

    private function authenticateAs(User $user): string
    {
        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        return $jwtManager->create($user);
    }

    private function getAuthHeaders(?string $token = null): array
    {
        $token = $token ?? $this->authToken;
        if (!$token) {
            return [];
        }

        return [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/ld+json'
        ];
    }

    public function testCreateInvitationWhenSlotsAvailable(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test client with 4 slots
        $testClient = $this->createTestClient('test-slots-available-' . uniqid(), 4);

        // Create admin user who will send the invitation
        $adminUser = $this->createTestUser('admin-' . uniqid() . '@slots-test.com', $testClient, true, ['ROLE_CLIENT_ADMIN']);

        // Create 2 active users (admin + 2 users = 3/4 slots used, leaving 1 slot available)
        $this->createTestUser('user1-' . uniqid() . '@slots-test.com', $testClient, true);
        $this->createTestUser('user2-' . uniqid() . '@slots-test.com', $testClient, true);

        // Authenticate as admin user
        $token = $this->authenticateAs($adminUser);

        $requestData = [
            'email' => 'newuser-' . uniqid() . '@slots-test.com',
            'firstName' => 'New',
            'lastName' => 'User',
            'roles' => ['ROLE_USER', 'ROLE_CLIENT'],
            'client' => '/api/v1/clients/' . $testClient->getId()->toRfc4122()
        ];

        $this->client->request(
            'POST',
            '/api/v1/user_invitations',
            [],
            [],
            array_merge($this->getAuthHeaders($token), ['CONTENT_TYPE' => 'application/ld+json']),
            json_encode($requestData)
        );

        // Should succeed because 1 slot is available (3/4 used)
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('@slots-test.com', $responseData['email']);
    }

    public function testCreateInvitationWhenNoSlotsAvailable(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test client with 3 slots
        $testClient = $this->createTestClient('test-slots-full-' . uniqid(), 3);

        // Create admin user who will send the invitation
        $adminUser = $this->createTestUser('admin-' . uniqid() . '@slots-full.com', $testClient, true, ['ROLE_CLIENT_ADMIN']);

        // Create 2 active users (admin + 2 users = 3/3 slots filled, no slots available)
        $this->createTestUser('user1-' . uniqid() . '@slots-full.com', $testClient, true);
        $this->createTestUser('user2-' . uniqid() . '@slots-full.com', $testClient, true);

        // Authenticate as admin user
        $token = $this->authenticateAs($adminUser);

        $requestData = [
            'email' => 'newuser-' . uniqid() . '@slots-full.com',
            'firstName' => 'New',
            'lastName' => 'User',
            'roles' => ['ROLE_USER', 'ROLE_CLIENT'],
            'client' => '/api/v1/clients/' . $testClient->getId()->toRfc4122()
        ];

        $this->client->request(
            'POST',
            '/api/v1/user_invitations',
            [],
            [],
            array_merge($this->getAuthHeaders($token), ['CONTENT_TYPE' => 'application/ld+json']),
            json_encode($requestData)
        );

        // Should fail because no slots available (3/3 used)
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('maximum number of active users', $responseData['hydra:description'] ?? $responseData['detail'] ?? '');
        $this->assertStringContainsString('3/3', $responseData['hydra:description'] ?? $responseData['detail'] ?? '');
    }

    public function testCreateInvitationWithInactiveUsersNotCountedAgainstLimit(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test client with 3 slots
        $testClient = $this->createTestClient('test-slots-inactive-' . uniqid(), 3);

        // Create admin user
        $adminUser = $this->createTestUser('admin-' . uniqid() . '@slots-inactive.com', $testClient, true, ['ROLE_CLIENT_ADMIN']);

        // Create 2 users: 1 active, 1 inactive (admin + 1 active = 2/3 slots used, 1 inactive doesn't count)
        $this->createTestUser('active-' . uniqid() . '@slots-inactive.com', $testClient, true);
        $this->createTestUser('inactive-' . uniqid() . '@slots-inactive.com', $testClient, false);

        // Authenticate as admin user
        $token = $this->authenticateAs($adminUser);

        $requestData = [
            'email' => 'newuser-' . uniqid() . '@slots-inactive.com',
            'firstName' => 'New',
            'lastName' => 'User',
            'roles' => ['ROLE_USER', 'ROLE_CLIENT'],
            'client' => '/api/v1/clients/' . $testClient->getId()->toRfc4122()
        ];

        $this->client->request(
            'POST',
            '/api/v1/user_invitations',
            [],
            [],
            array_merge($this->getAuthHeaders($token), ['CONTENT_TYPE' => 'application/ld+json']),
            json_encode($requestData)
        );

        // Should succeed because only 2 active users (admin + 1 active, inactive users don't count)
        $this->assertResponseIsSuccessful();
    }

    public function testCreateInvitationWithUnlimitedSlots(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test client with null maxActiveUsers (unlimited)
        $testClient = $this->createTestClient('test-slots-unlimited-' . uniqid(), null);

        // Create admin user
        $adminUser = $this->createTestUser('admin-' . uniqid() . '@unlimited.com', $testClient, true, ['ROLE_CLIENT_ADMIN']);

        // Create many active users
        for ($i = 1; $i <= 10; $i++) {
            $this->createTestUser("user{$i}-" . uniqid() . "@unlimited.com", $testClient, true);
        }

        // Authenticate as admin user
        $token = $this->authenticateAs($adminUser);

        $requestData = [
            'email' => 'newuser-' . uniqid() . '@unlimited.com',
            'firstName' => 'New',
            'lastName' => 'User',
            'roles' => ['ROLE_USER', 'ROLE_CLIENT'],
            'client' => '/api/v1/clients/' . $testClient->getId()->toRfc4122()
        ];

        $this->client->request(
            'POST',
            '/api/v1/user_invitations',
            [],
            [],
            array_merge($this->getAuthHeaders($token), ['CONTENT_TYPE' => 'application/ld+json']),
            json_encode($requestData)
        );

        // Should succeed because maxActiveUsers is null (unlimited)
        $this->assertResponseIsSuccessful();
    }

    public function testCompleteInvitationWhenSlotsAvailable(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test client with 4 slots
        $testClient = $this->createTestClient('test-complete-ok-' . uniqid(), 4);

        // Create admin and invitation
        $adminUser = $this->createTestUser('admin-' . uniqid() . '@complete-ok.com', $testClient, true, ['ROLE_CLIENT_ADMIN']);
        $invitation = $this->createTestInvitation('newuser-' . uniqid() . '@complete-ok.com', $testClient, $adminUser);

        // Create 2 active users (admin + 2 users = 3/4 slots used, leaving 1 slot available)
        $this->createTestUser('user1-' . uniqid() . '@complete-ok.com', $testClient, true);
        $this->createTestUser('user2-' . uniqid() . '@complete-ok.com', $testClient, true);

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

        // Should succeed because 1 slot is available
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
    }

    public function testCompleteInvitationWhenNoSlotsAvailable(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test client with 3 slots
        $testClient = $this->createTestClient('test-complete-full-' . uniqid(), 3);

        // Create admin and invitation FIRST (while slots available)
        $adminUser = $this->createTestUser('admin-' . uniqid() . '@complete-full.com', $testClient, true, ['ROLE_CLIENT_ADMIN']);
        $invitation = $this->createTestInvitation('newuser-' . uniqid() . '@complete-full.com', $testClient, $adminUser);

        // NOW fill all slots with active users (admin + 2 users = 3/3 slots full)
        $this->createTestUser('user1-' . uniqid() . '@complete-full.com', $testClient, true);
        $this->createTestUser('user2-' . uniqid() . '@complete-full.com', $testClient, true);

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

        // Should fail because no slots available when trying to complete
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('maximum number of active users', $responseData['detail']);
        $this->assertStringContainsString('3/3', $responseData['detail']);
    }

    public function testCompleteInvitationWithUnlimitedSlots(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test client with unlimited slots
        $testClient = $this->createTestClient('test-complete-unlimited-' . uniqid(), null);

        // Create many users
        for ($i = 1; $i <= 10; $i++) {
            $this->createTestUser("user{$i}-" . uniqid() . "@complete-unlimited.com", $testClient, true);
        }

        // Create admin and invitation
        $adminUser = $this->createTestUser('admin-' . uniqid() . '@complete-unlimited.com', $testClient, true, ['ROLE_CLIENT_ADMIN']);
        $invitation = $this->createTestInvitation('newuser-' . uniqid() . '@complete-unlimited.com', $testClient, $adminUser);

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

        // Should succeed because unlimited slots
        $this->assertResponseIsSuccessful();
    }

    // Helper methods

    private function createTestClient(string $code, ?int $maxActiveUsers): Client
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $client = new Client();
        $client->setName('Test Client ' . $code);
        $client->setCode($code);
        $client->setIsActive(true);
        $client->setMaxActiveUsers($maxActiveUsers);

        $entityManager->persist($client);
        $entityManager->flush();

        return $client;
    }

    private function createTestUser(string $email, Client $client, bool $isActive, array $roles = ['ROLE_USER', 'ROLE_CLIENT']): User
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRoles($roles);
        $user->setClient($client);
        $user->setIsActive($isActive);

        // Set a dummy password
        $passwordHasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($passwordHasher->hashPassword($user, 'TestPassword123'));

        $entityManager->persist($user);
        $entityManager->flush();

        // Refresh the client to update the users collection
        $entityManager->refresh($client);

        return $user;
    }

    private function createTestInvitation(string $email, Client $client, User $createdBy): UserInvitation
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $invitation = new UserInvitation();
        $invitation->setEmail($email);
        $invitation->setFirstName('Test');
        $invitation->setLastName('User');
        $invitation->generateToken();
        $invitation->setDefaultExpiration(7);
        $invitation->setStatus(UserInvitation::STATUS_PENDING);
        $invitation->setRoles(['ROLE_USER', 'ROLE_CLIENT']);
        $invitation->setClient($client);
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

            try {
                // Delete test users
                $connection->executeStatement(
                    "DELETE FROM user WHERE email LIKE '%@slots-test.com'
                     OR email LIKE '%@slots-full.com'
                     OR email LIKE '%@slots-inactive.com'
                     OR email LIKE '%@unlimited.com'
                     OR email LIKE '%@complete-ok.com'
                     OR email LIKE '%@complete-full.com'
                     OR email LIKE '%@complete-unlimited.com'"
                );

                // Delete test invitations
                $connection->executeStatement(
                    "DELETE FROM user_invitation WHERE email LIKE '%@slots-test.com'
                     OR email LIKE '%@slots-full.com'
                     OR email LIKE '%@slots-inactive.com'
                     OR email LIKE '%@unlimited.com'
                     OR email LIKE '%@complete-ok.com'
                     OR email LIKE '%@complete-full.com'
                     OR email LIKE '%@complete-unlimited.com'"
                );

                // Delete test clients
                $connection->executeStatement(
                    "DELETE FROM client WHERE code LIKE 'test-slots-%'
                     OR code LIKE 'test-complete-%'"
                );
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }
}
