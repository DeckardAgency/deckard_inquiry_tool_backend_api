<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\DashboardResource;
use App\Service\DashboardService;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardProvider implements ProviderInterface
{
    public function __construct(
        private DashboardService $dashboardService,
        private RequestStack $requestStack
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get date range from query parameters
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');

        if (!$startDate || !$endDate) {
            // Default to current year if no dates provided
            $startDate = new \DateTime('first day of January this year');
            $endDate = new \DateTime('last day of December this year');
        } else {
            $startDate = new \DateTime($startDate);
            $endDate = new \DateTime($endDate);
        }

        $data = $this->dashboardService->getPerformanceOverview($startDate, $endDate);

        $resource = new DashboardResource();
        $resource->period = $data['period'];
        $resource->shopOrders = $data['shopOrders'];
        $resource->manualInquiries = $data['manualInquiries'];
        $resource->activeInquiries = $data['activeInquiries'];
        $resource->cancelledInquiries = $data['cancelledInquiries'];
        $resource->activeCarts = $data['activeCarts'];
        $resource->completedCarts = $data['completedCarts'];
        $resource->totalShopRevenue = $data['totalShopRevenue'];
        $resource->cancelledOrdersRevenue = $data['cancelledOrdersRevenue'];

        return $resource;
    }
}
