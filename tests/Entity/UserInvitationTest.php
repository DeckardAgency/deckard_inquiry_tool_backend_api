<?php

namespace App\Tests\Entity;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\UserInvitation;
use PHPUnit\Framework\TestCase;

class UserInvitationTest extends TestCase
{
    public function testConstructorGeneratesIdAndToken(): void
    {
        $invitation = new UserInvitation();

        $this->assertNotNull($invitation->getId());
        $this->assertNotNull($invitation->getToken());
        $this->assertEquals(128, strlen($invitation->getToken())); // 64 bytes = 128 hex chars
        $this->assertNotNull($invitation->getExpiresAt());
    }

    public function testSettersAndGetters(): void
    {
        $invitation = new UserInvitation();
        $client = new Client();
        $user = new User();

        $invitation->setEmail('test@example.com');
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');
        $invitation->setClient($client);
        $invitation->setCreatedBy($user);
        $invitation->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $this->assertEquals('test@example.com', $invitation->getEmail());
        $this->assertEquals('John', $invitation->getFirstName());
        $this->assertEquals('Doe', $invitation->getLastName());
        $this->assertEquals($client, $invitation->getClient());
        $this->assertEquals($user, $invitation->getCreatedBy());
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $invitation->getRoles());
    }

    public function testGetFullName(): void
    {
        $invitation = new UserInvitation();
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');

        $this->assertEquals('John Doe', $invitation->getFullName());
    }

    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', UserInvitation::STATUS_PENDING);
        $this->assertEquals('completed', UserInvitation::STATUS_COMPLETED);
        $this->assertEquals('expired', UserInvitation::STATUS_EXPIRED);
        $this->assertEquals('revoked', UserInvitation::STATUS_REVOKED);
    }

    public function testDefaultStatus(): void
    {
        $invitation = new UserInvitation();

        $this->assertEquals(UserInvitation::STATUS_PENDING, $invitation->getStatus());
        $this->assertTrue($invitation->isPending());
    }

    public function testSetStatus(): void
    {
        $invitation = new UserInvitation();

        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);
        $this->assertEquals(UserInvitation::STATUS_COMPLETED, $invitation->getStatus());

        $invitation->setStatus(UserInvitation::STATUS_REVOKED);
        $this->assertEquals(UserInvitation::STATUS_REVOKED, $invitation->getStatus());
    }

    public function testSetStatusThrowsExceptionForInvalidStatus(): void
    {
        $invitation = new UserInvitation();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status: invalid_status');

        $invitation->setStatus('invalid_status');
    }

    public function testGenerateToken(): void
    {
        $invitation = new UserInvitation();
        $originalToken = $invitation->getToken();

        $invitation->generateToken();
        $newToken = $invitation->getToken();

        $this->assertNotEquals($originalToken, $newToken);
        $this->assertEquals(128, strlen($newToken));
    }

    public function testSetDefaultExpiration(): void
    {
        $invitation = new UserInvitation();
        $now = new \DateTime();

        $invitation->setDefaultExpiration(7);

        $expiresAt = $invitation->getExpiresAt();
        $this->assertNotNull($expiresAt);

        $diff = $now->diff($expiresAt);
        $this->assertEquals(7, $diff->days);
    }

    public function testSetDefaultExpirationWithCustomDays(): void
    {
        $invitation = new UserInvitation();

        $invitation->setDefaultExpiration(14);

        $expiresAt = $invitation->getExpiresAt();
        $now = new \DateTime();
        $diff = $now->diff($expiresAt);

        // Allow for timing differences - should be 13 or 14 days
        $this->assertGreaterThanOrEqual(13, $diff->days);
        $this->assertLessThanOrEqual(14, $diff->days);
    }

    public function testIsExpired(): void
    {
        $invitation = new UserInvitation();

        // Not expired (future date)
        $invitation->setExpiresAt(new \DateTime('+1 day'));
        $this->assertFalse($invitation->isExpired());

        // Expired (past date)
        $invitation->setExpiresAt(new \DateTime('-1 day'));
        $this->assertTrue($invitation->isExpired());
    }

    public function testIsPending(): void
    {
        $invitation = new UserInvitation();

        $this->assertTrue($invitation->isPending());

        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);
        $this->assertFalse($invitation->isPending());
    }

    public function testIsCompleted(): void
    {
        $invitation = new UserInvitation();

        $this->assertFalse($invitation->isCompleted());

        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);
        $this->assertTrue($invitation->isCompleted());
    }

    public function testIsRevoked(): void
    {
        $invitation = new UserInvitation();

        $this->assertFalse($invitation->isRevoked());

        $invitation->setStatus(UserInvitation::STATUS_REVOKED);
        $this->assertTrue($invitation->isRevoked());
    }

    public function testCanBeCompleted(): void
    {
        $invitation = new UserInvitation();
        $invitation->setExpiresAt(new \DateTime('+1 day'));

        // Pending and not expired
        $this->assertTrue($invitation->canBeCompleted());

        // Expired
        $invitation->setExpiresAt(new \DateTime('-1 day'));
        $this->assertFalse($invitation->canBeCompleted());

        // Already completed
        $invitation->setExpiresAt(new \DateTime('+1 day'));
        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);
        $this->assertFalse($invitation->canBeCompleted());

        // Revoked
        $invitation->setStatus(UserInvitation::STATUS_REVOKED);
        $this->assertFalse($invitation->canBeCompleted());
    }

    public function testMarkAsCompleted(): void
    {
        $invitation = new UserInvitation();
        $invitation->setExpiresAt(new \DateTime('+1 day'));

        $this->assertNull($invitation->getCompletedAt());

        $invitation->markAsCompleted();

        $this->assertEquals(UserInvitation::STATUS_COMPLETED, $invitation->getStatus());
        $this->assertNotNull($invitation->getCompletedAt());
        $this->assertTrue($invitation->isCompleted());
    }

    public function testMarkAsCompletedThrowsExceptionWhenCannotBeCompleted(): void
    {
        $invitation = new UserInvitation();
        $invitation->setExpiresAt(new \DateTime('-1 day')); // Expired

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invitation cannot be completed');

        $invitation->markAsCompleted();
    }

    public function testMarkAsRevoked(): void
    {
        $invitation = new UserInvitation();
        $invitation->setExpiresAt(new \DateTime('+1 day'));

        $invitation->markAsRevoked();

        $this->assertEquals(UserInvitation::STATUS_REVOKED, $invitation->getStatus());
        $this->assertTrue($invitation->isRevoked());
    }

    public function testMarkAsRevokedThrowsExceptionWhenCompleted(): void
    {
        $invitation = new UserInvitation();
        $invitation->setExpiresAt(new \DateTime('+1 day'));
        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot revoke a completed invitation');

        $invitation->markAsRevoked();
    }

    public function testMarkAsExpired(): void
    {
        $invitation = new UserInvitation();

        $invitation->markAsExpired();

        $this->assertEquals(UserInvitation::STATUS_EXPIRED, $invitation->getStatus());
    }

    public function testMarkAsExpiredThrowsExceptionWhenCompleted(): void
    {
        $invitation = new UserInvitation();
        $invitation->setExpiresAt(new \DateTime('+1 day'));
        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot expire a completed invitation');

        $invitation->markAsExpired();
    }

    public function testGetIsExpired(): void
    {
        $invitation = new UserInvitation();

        $invitation->setExpiresAt(new \DateTime('+1 day'));
        $this->assertFalse($invitation->getIsExpired());

        $invitation->setExpiresAt(new \DateTime('-1 day'));
        $this->assertTrue($invitation->getIsExpired());
    }

    public function testTimestamps(): void
    {
        $invitation = new UserInvitation();

        $createdAt = new \DateTime('2025-01-01 10:00:00');
        $updatedAt = new \DateTime('2025-01-02 10:00:00');

        $invitation->setCreatedAt($createdAt);
        $invitation->setUpdatedAt($updatedAt);

        $this->assertEquals($createdAt, $invitation->getCreatedAt());
        $this->assertEquals($updatedAt, $invitation->getUpdatedAt());
    }
}
