<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * Custom filter for UUID fields that handles the binary UUID format properly
 */
class UuidSearchFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        // Check if the property is configured for filtering
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        // Handle nested properties (e.g., 'inquiry.id')
        $properties = explode('.', $property);

        if (count($properties) === 2) {
            [$relation, $field] = $properties;

            // Get the root alias
            $alias = $queryBuilder->getRootAliases()[0];

            // Generate unique parameter name
            $parameterName = $queryNameGenerator->generateParameterName($property);
            $joinAlias = $queryNameGenerator->generateJoinAlias($relation);

            // Check if join already exists
            $existingJoins = $queryBuilder->getDQLPart('join');
            $joinExists = false;

            if (isset($existingJoins[$alias])) {
                foreach ($existingJoins[$alias] as $join) {
                    if ($join->getAlias() === $joinAlias) {
                        $joinExists = true;
                        break;
                    }
                }
            }

            if (!$joinExists) {
                $queryBuilder->leftJoin(sprintf('%s.%s', $alias, $relation), $joinAlias);
            }

            // Handle array of values
            if (is_array($value)) {
                $uuids = array_map(function ($v) {
                    return $this->convertToUuidBinary($v);
                }, $value);
                $uuids = array_filter($uuids); // Remove nulls

                if (!empty($uuids)) {
                    $queryBuilder
                        ->andWhere(sprintf('%s.%s IN (:%s)', $joinAlias, $field, $parameterName))
                        ->setParameter($parameterName, $uuids);
                }
            } else {
                $uuid = $this->convertToUuidBinary($value);
                if ($uuid !== null) {
                    $queryBuilder
                        ->andWhere(sprintf('%s.%s = :%s', $joinAlias, $field, $parameterName))
                        ->setParameter($parameterName, $uuid, 'uuid');
                }
            }
        } elseif (count($properties) === 1) {
            // Direct property on the entity
            $alias = $queryBuilder->getRootAliases()[0];
            $parameterName = $queryNameGenerator->generateParameterName($property);

            if (is_array($value)) {
                $uuids = array_map(function ($v) {
                    return $this->convertToUuidBinary($v);
                }, $value);
                $uuids = array_filter($uuids);

                if (!empty($uuids)) {
                    $queryBuilder
                        ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $property, $parameterName))
                        ->setParameter($parameterName, $uuids);
                }
            } else {
                $uuid = $this->convertToUuidBinary($value);
                if ($uuid !== null) {
                    $queryBuilder
                        ->andWhere(sprintf('%s.%s = :%s', $alias, $property, $parameterName))
                        ->setParameter($parameterName, $uuid, 'uuid');
                }
            }
        }
    }

    /**
     * Convert a string value to a Uuid object for use with Doctrine's UUID type
     */
    private function convertToUuidBinary(string $value): ?Uuid
    {
        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $strategy) {
            $description[$property] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by UUID',
            ];

            // Also add array variant
            $description[$property . '[]'] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'is_collection' => true,
                'description' => 'Filter by multiple UUIDs',
            ];
        }

        return $description;
    }
}
