<?php

namespace App\Validator;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ActiveUserLimitValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ActiveUserLimit) {
            throw new UnexpectedTypeException($constraint, ActiveUserLimit::class);
        }

        if (!$value instanceof User) {
            throw new UnexpectedValueException($value, User::class);
        }

        // If the user is not being set to active, no validation needed
        if (!$value->getIsActive()) {
            return;
        }

        // If the user doesn't belong to a client, no validation needed
        $client = $value->getClient();
        if (!$client) {
            return;
        }

        // If the client has no limit (null), allow unlimited active users
        $maxActiveUsers = $client->getMaxActiveUsers();
        if ($maxActiveUsers === null) {
            return;
        }

        // Get the UnitOfWork to check if this is an update or create
        $uow = $this->entityManager->getUnitOfWork();
        $originalData = $uow->getOriginalEntityData($value);

        // If this is an update and the user was already active, no need to count again
        if (!empty($originalData) && isset($originalData['isActive']) && $originalData['isActive'] === true) {
            return;
        }

        // Count current active users for this client, excluding this user
        $currentActiveCount = $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.client = :client')
            ->andWhere('u.isActive = :active')
            ->andWhere('u.id != :userId')
            ->setParameter('client', $client)
            ->setParameter('active', true)
            ->setParameter('userId', $value->getId())
            ->getQuery()
            ->getSingleScalarResult();

        // Check if adding this user would exceed the limit
        if ($currentActiveCount >= $maxActiveUsers) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ company }}', $client->getName())
                ->setParameter('{{ limit }}', (string) $maxActiveUsers)
                ->addViolation();
        }
    }
}
