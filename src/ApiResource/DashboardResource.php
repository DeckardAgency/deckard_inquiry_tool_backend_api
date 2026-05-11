<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model;
use App\State\DashboardProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Dashboard',
    description: 'Dashboard performance metrics',
    operations: [
        new Get(
            uriTemplate: '/dashboard/performance',
            openapi: new Model\Operation(
                tags: ['Dashboard'],
                summary: 'Get performance overview metrics',
                description: 'Returns key performance indicators including orders, inquiries, carts and revenue metrics with percentage changes',
                parameters: [
                    new Model\Parameter(
                        name: 'startDate',
                        in: 'query',
                        description: 'Start date for the period (Y-m-d format)',
                        required: false,
                        schema: ['type' => 'string', 'format' => 'date']
                    ),
                    new Model\Parameter(
                        name: 'endDate',
                        in: 'query',
                        description: 'End date for the period (Y-m-d format)',
                        required: false,
                        schema: ['type' => 'string', 'format' => 'date']
                    )
                ]
            ),
            provider: DashboardProvider::class
        )
    ]
)]
class DashboardResource
{
    #[Groups(['dashboard:read'])]
    public array $period;

    #[Groups(['dashboard:read'])]
    public array $shopOrders;

    #[Groups(['dashboard:read'])]
    public array $manualInquiries;

    #[Groups(['dashboard:read'])]
    public array $activeInquiries;

    #[Groups(['dashboard:read'])]
    public array $cancelledInquiries;

    #[Groups(['dashboard:read'])]
    public array $activeCarts;

    #[Groups(['dashboard:read'])]
    public array $completedCarts;

    #[Groups(['dashboard:read'])]
    public array $totalShopRevenue;

    #[Groups(['dashboard:read'])]
    public array $cancelledOrdersRevenue;
}
