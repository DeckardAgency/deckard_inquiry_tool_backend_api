<?php

namespace App\Tests\State\Processor;

use ApiPlatform\Metadata\Post;
use App\Entity\Client;
use App\Entity\User;
use App\Entity\UserInvitation;
use App\Message\UserInvitedMessage;
use App\Repository\UserInvitationRepository;
use App\Repository\UserRepository;
use App\State\Processor\InvitationCreatedProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class InvitationCreatedProcessorTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private MessageBusInterface $messageBus;
    private UserRepository $userRepository;
    private UserInvitationRepository $userInvitationRepository;
    private LoggerInterface $logger;
    private InvitationCreatedProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userInvitationRepository = $this->createMock(UserInvitationRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new InvitationCreatedProcessor(
            $this->entityManager,
            $this->security,
            $this->messageBus,
            $this->userRepository,
            $this->userInvitationRepository,
            $this->logger,
            7 // expiration days
        );
    }

    public function testProcessCreatesInvitationSuccessfully(): void
    {
        // Arrange
        $invitation = new UserInvitation();
        $invitation->setEmail('test@example.com');
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');

        $admin = $this->createTestUser();

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $this->userInvitationRepository
            ->expects($this->once())
            ->method('findPendingByEmail')
            ->with('test@example.com')
            ->willReturn(null);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($admin);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($invitation);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(UserInvitedMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $result = $this->processor->process($invitation, new Post(), []);

        // Assert
        $this->assertSame($invitation, $result);
        $this->assertNotNull($invitation->getToken());
        $this->assertNotNull($invitation->getExpiresAt());
        $this->assertEquals($admin, $invitation->getCreatedBy());
    }

    public function testProcessThrowsExceptionWhenEmailAlreadyRegistered(): void
    {
        // Arrange
        $invitation = new UserInvitation();
        $invitation->setEmail('existing@example.com');

        $existingUser = new User();
        $existingUser->setEmail('existing@example.com');

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'existing@example.com'])
            ->willReturn($existingUser);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('User with email "existing@example.com" already exists');

        // Act
        $this->processor->process($invitation, new Post(), []);
    }

    public function testProcessThrowsExceptionWhenPendingInvitationExists(): void
    {
        // Arrange
        $invitation = new UserInvitation();
        $invitation->setEmail('pending@example.com');

        $existingInvitation = new UserInvitation();

        $this->userRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->userInvitationRepository
            ->expects($this->once())
            ->method('findPendingByEmail')
            ->with('pending@example.com')
            ->willReturn($existingInvitation);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('A pending invitation already exists for email "pending@example.com"');

        // Act
        $this->processor->process($invitation, new Post(), []);
    }

    public function testProcessSetsDefaultRolesWhenNotProvided(): void
    {
        // Arrange
        $invitation = new UserInvitation();
        $invitation->setEmail('test@example.com');
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');
        $invitation->setRoles([]); // Empty roles

        $admin = $this->createTestUser();

        $this->userRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->userInvitationRepository
            ->method('findPendingByEmail')
            ->willReturn(null);

        $this->security
            ->method('getUser')
            ->willReturn($admin);

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $result = $this->processor->process($invitation, new Post(), []);

        // Assert
        $this->assertEquals(['ROLE_USER'], $result->getRoles());
    }

    public function testProcessKeepsCustomRoles(): void
    {
        // Arrange
        $invitation = new UserInvitation();
        $invitation->setEmail('test@example.com');
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');
        $invitation->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $admin = $this->createTestUser();

        $this->userRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->userInvitationRepository
            ->method('findPendingByEmail')
            ->willReturn(null);

        $this->security
            ->method('getUser')
            ->willReturn($admin);

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $result = $this->processor->process($invitation, new Post(), []);

        // Assert
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $result->getRoles());
    }

    public function testProcessHandlesMessageDispatchFailureGracefully(): void
    {
        // Arrange
        $invitation = new UserInvitation();
        $invitation->setEmail('test@example.com');
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');

        $admin = $this->createTestUser();

        $this->userRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->userInvitationRepository
            ->method('findPendingByEmail')
            ->willReturn(null);

        $this->security
            ->method('getUser')
            ->willReturn($admin);

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \Exception('Message bus error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to dispatch UserInvitedMessage',
                $this->arrayHasKey('error')
            );

        // Act - should not throw exception
        $result = $this->processor->process($invitation, new Post(), []);

        // Assert
        $this->assertSame($invitation, $result);
    }

    public function testProcessSetsExpirationDate(): void
    {
        // Arrange
        $invitation = new UserInvitation();
        $invitation->setEmail('test@example.com');
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');

        $admin = $this->createTestUser();

        $this->userRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->userInvitationRepository
            ->method('findPendingByEmail')
            ->willReturn(null);

        $this->security
            ->method('getUser')
            ->willReturn($admin);

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $result = $this->processor->process($invitation, new Post(), []);

        // Assert
        $this->assertNotNull($result->getExpiresAt());
        $now = new \DateTime();
        $diff = $now->diff($result->getExpiresAt());
        // Allow 6 or 7 days due to timing differences
        $this->assertGreaterThanOrEqual(6, $diff->days);
        $this->assertLessThanOrEqual(7, $diff->days);
    }

    private function createTestUser(): User
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setCode('TEST');
        $client->setEmail('client@test.com');

        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['getId'])
            ->getMock();

        $userId = Uuid::v4();
        $user->method('getId')->willReturn($userId);
        $user->setEmail('admin@test.com');
        $user->setFirstName('Admin');
        $user->setLastName('User');
        $user->setPhoneNumber('+1234567890');
        $user->setClient($client);
        $user->setPassword('dummy');

        return $user;
    }
}
