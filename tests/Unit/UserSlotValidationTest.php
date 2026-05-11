<?php

namespace App\Tests\Unit;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\UserInvitation;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for user slot validation logic
 */
class UserSlotValidationTest extends KernelTestCase
{
    private ?UserRepository $userRepository = null;
    private ?ClientRepository $clientRepository = null;

    protected function setUp(): void
    {
        self::bootKernel();
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

    public function testClientCanAddUserWhenSlotsAvailable(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create client with 3 slots
        $client = new Client();
        $client->setName('Test Client Slots Available');
        $client->setCode('test-can-add-' . uniqid());
        $client->setIsActive(true);
        $client->setMaxActiveUsers(3);

        $entityManager->persist($client);
        $entityManager->flush();

        // Create 2 active users
        $this->createUser($client, 'user1-' . uniqid() . '@test.com', true);
        $this->createUser($client, 'user2-' . uniqid() . '@test.com', true);

        // Refresh client to load users collection
        $entityManager->refresh($client);

        // Should be able to add one more user (2/3 used)
        $this->assertTrue($client->canAddActiveUser());
        $this->assertEquals(2, $client->countActiveUsers());
    }

    public function testClientCannotAddUserWhenSlotsFull(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create client with 2 slots
        $client = new Client();
        $client->setName('Test Client Slots Full');
        $client->setCode('test-cannot-add-' . uniqid());
        $client->setIsActive(true);
        $client->setMaxActiveUsers(2);

        $entityManager->persist($client);
        $entityManager->flush();

        // Create 2 active users (fill all slots)
        $this->createUser($client, 'user1-' . uniqid() . '@full.com', true);
        $this->createUser($client, 'user2-' . uniqid() . '@full.com', true);

        // Refresh client to load users collection
        $entityManager->refresh($client);

        // Should NOT be able to add more users (2/2 used)
        $this->assertFalse($client->canAddActiveUser());
        $this->assertEquals(2, $client->countActiveUsers());
    }

    public function testClientInactiveUsersDoNotCountAgainstLimit(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create client with 2 slots
        $client = new Client();
        $client->setName('Test Client Inactive Users');
        $client->setCode('test-inactive-' . uniqid());
        $client->setIsActive(true);
        $client->setMaxActiveUsers(2);

        $entityManager->persist($client);
        $entityManager->flush();

        // Create 1 active and 2 inactive users
        $this->createUser($client, 'active-' . uniqid() . '@inactive-test.com', true);
        $this->createUser($client, 'inactive1-' . uniqid() . '@inactive-test.com', false);
        $this->createUser($client, 'inactive2-' . uniqid() . '@inactive-test.com', false);

        // Refresh client to load users collection
        $entityManager->refresh($client);

        // Should be able to add one more active user (only 1 active user counted)
        $this->assertTrue($client->canAddActiveUser());
        $this->assertEquals(1, $client->countActiveUsers());
    }

    public function testClientWithUnlimitedSlotsCanAlwaysAddUsers(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create client with unlimited slots (null)
        $client = new Client();
        $client->setName('Test Client Unlimited');
        $client->setCode('test-unlimited-' . uniqid());
        $client->setIsActive(true);
        $client->setMaxActiveUsers(null);  // Unlimited

        $entityManager->persist($client);
        $entityManager->flush();

        // Create many active users
        for ($i = 0; $i < 10; $i++) {
            $this->createUser($client, "user{$i}-" . uniqid() . "@unlimited.com", true);
        }

        // Refresh client to load users collection
        $entityManager->refresh($client);

        // Should always be able to add more users
        $this->assertTrue($client->canAddActiveUser());
        $this->assertEquals(10, $client->countActiveUsers());
    }

    public function testClientCountActiveUsersCorrectly(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create client
        $client = new Client();
        $client->setName('Test Client Count Users');
        $client->setCode('test-count-' . uniqid());
        $client->setIsActive(true);
        $client->setMaxActiveUsers(10);

        $entityManager->persist($client);
        $entityManager->flush();

        // Create 3 active and 2 inactive users
        $this->createUser($client, 'active1-' . uniqid() . '@count.com', true);
        $this->createUser($client, 'active2-' . uniqid() . '@count.com', true);
        $this->createUser($client, 'active3-' . uniqid() . '@count.com', true);
        $this->createUser($client, 'inactive1-' . uniqid() . '@count.com', false);
        $this->createUser($client, 'inactive2-' . uniqid() . '@count.com', false);

        // Refresh client to load users collection
        $entityManager->refresh($client);

        // Should count only 3 active users
        $this->assertEquals(3, $client->countActiveUsers());
        $this->assertTrue($client->canAddActiveUser());
    }

    // Helper method

    private function createUser(Client $client, string $email, bool $isActive): User
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER', 'ROLE_CLIENT']);
        $user->setClient($client);
        $user->setIsActive($isActive);

        // Set a dummy password
        $passwordHasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($passwordHasher->hashPassword($user, 'TestPassword123'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test data
        if (self::$booted) {
            $entityManager = static::getContainer()->get('doctrine')->getManager();
            $connection = $entityManager->getConnection();

            try {
                // Delete test data with foreign key safe approach
                $connection->executeStatement("SET FOREIGN_KEY_CHECKS=0");
                $connection->executeStatement("DELETE FROM user WHERE email LIKE '%@test.com' OR email LIKE '%@full.com' OR email LIKE '%@inactive-test.com' OR email LIKE '%@unlimited.com' OR email LIKE '%@count.com'");
                $connection->executeStatement("DELETE FROM client WHERE code LIKE 'test-%'");
                $connection->executeStatement("SET FOREIGN_KEY_CHECKS=1");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
}
