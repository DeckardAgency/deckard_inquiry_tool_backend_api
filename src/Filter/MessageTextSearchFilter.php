<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Custom filter to search within message text of info requests
 * Searches in InquiryPartInfoMessage.messageText for info requests that contain matching messages
 */
class MessageTextSearchFilter extends AbstractFilter
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
        // Only process the 'messageText' property
        if ($property !== 'messageText') {
            return;
        }

        // Skip empty values
        if (empty($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName('messageText');
        $joinAlias = $queryNameGenerator->generateJoinAlias('messages');

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
            $queryBuilder->leftJoin(sprintf('%s.messages', $alias), $joinAlias);
        }

        // Search for partial match in message text (case-insensitive)
        $queryBuilder
            ->andWhere(sprintf('LOWER(%s.messageText) LIKE LOWER(:%s)', $joinAlias, $parameterName))
            ->setParameter($parameterName, '%' . $value . '%');

        // Make sure we get distinct results since one info request can have multiple matching messages
        $queryBuilder->distinct();
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'messageText' => [
                'property' => 'messageText',
                'type' => 'string',
                'required' => false,
                'description' => 'Search within message text content',
                'openapi' => [
                    'example' => 'search term',
                ],
            ],
        ];
    }
}
