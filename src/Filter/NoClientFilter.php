<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;

final class NoClientFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
               $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        // This filter only works on the 'hasClient' virtual property
        if ($property !== 'hasClient') {
            return;
        }

        // Convert value to boolean
        $hasClient = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($hasClient === null) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if ($hasClient === false) {
            // Filter users WITHOUT a client
            $queryBuilder->andWhere(sprintf('%s.client IS NULL', $rootAlias));
        } else {
            // Filter users WITH a client
            $queryBuilder->andWhere(sprintf('%s.client IS NOT NULL', $rootAlias));
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'hasClient' => [
                'property' => 'hasClient',
                'type' => Type::BUILTIN_TYPE_BOOL,
                'required' => false,
                'description' => 'Filter users by client presence (true: with client, false: without client)',
                'openapi' => [
                    'example' => 'false',
                    'allowReserved' => false,
                    'allowEmptyValue' => false,
                    'explode' => false,
                ],
            ],
        ];
    }
}
