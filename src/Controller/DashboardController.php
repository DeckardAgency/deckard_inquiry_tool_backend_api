<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Inquiry;
use App\Service\DashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DashboardService $dashboardService
    ) {}

    #[Route('/dashboard/performance-overview', name: 'api_dashboard_performance_overview', methods: ['GET'])]
    public function performanceOverview(Request $request): JsonResponse
    {
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

        // Get previous period for comparison
        $interval = $startDate->diff($endDate);
        $previousStartDate = clone $startDate;
        $previousEndDate = clone $endDate;
        $previousStartDate->sub($interval);
        $previousEndDate->sub($interval);

        // Get current period data
        $currentData = $this->getPerformanceData($startDate, $endDate);

        // Get previous period data for comparison
        $previousData = $this->getPerformanceData($previousStartDate, $previousEndDate);

        // Calculate percentage changes
        $response = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'shopOrders' => [
                'value' => $currentData['shopOrders'],
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['shopOrders'],
                    $currentData['shopOrders']
                )
            ],
            'manualInquiries' => [
                'value' => $currentData['manualInquiries'],
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['manualInquiries'],
                    $currentData['manualInquiries']
                )
            ],
            'activeInquiries' => [
                'value' => $currentData['activeInquiries'],
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['activeInquiries'],
                    $currentData['activeInquiries']
                )
            ],
            'cancelledInquiries' => [
                'value' => $currentData['cancelledInquiries'],
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['cancelledInquiries'],
                    $currentData['cancelledInquiries']
                )
            ],
            'activeCarts' => [
                'value' => $currentData['activeCarts'],
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['activeCarts'],
                    $currentData['activeCarts']
                )
            ],
            'completedCarts' => [
                'value' => $currentData['completedCarts'],
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['completedCarts'],
                    $currentData['completedCarts']
                )
            ],
            'totalShopRevenue' => [
                'value' => $currentData['totalShopRevenue'],
                'formatted' => number_format($currentData['totalShopRevenue'], 2, ',', '.') . ' €',
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['totalShopRevenue'],
                    $currentData['totalShopRevenue']
                )
            ],
            'cancelledOrdersRevenue' => [
                'value' => $currentData['cancelledOrdersRevenue'],
                'formatted' => number_format($currentData['cancelledOrdersRevenue'], 2, ',', '.') . ' €',
                'percentageChange' => $this->calculatePercentageChange(
                    $previousData['cancelledOrdersRevenue'],
                    $currentData['cancelledOrdersRevenue']
                )
            ]
        ];

        return $this->json($response);
    }

    private function getPerformanceData(\DateTime $startDate, \DateTime $endDate): array
    {
        $conn = $this->entityManager->getConnection();

        // Use native SQL for better performance with a single query
        $sql = '
            SELECT 
                -- Shop orders (non-draft, non-cancelled)
                (SELECT COUNT(*) 
                 FROM `order` 
                 WHERE created_at >= :startDate 
                 AND created_at <= :endDate
                 AND status NOT IN (:draftStatus, :cancelledStatus)) as shop_orders,
                
                -- Manual inquiries (non-draft)
                (SELECT COUNT(*) 
                 FROM inquiry 
                 WHERE created_at >= :startDate 
                 AND created_at <= :endDate
                 AND is_draft = 0) as manual_inquiries,
                
                -- Active inquiries
                (SELECT COUNT(*)
                 FROM inquiry
                 WHERE created_at >= :startDate
                 AND created_at <= :endDate
                 AND status IN (:submittedStatus, :inReviewStatus, :moreInfoStatus, :infoProvidedStatus, :inProgressStatus)) as active_inquiries,
                
                -- Cancelled inquiries
                (SELECT COUNT(*) 
                 FROM inquiry 
                 WHERE created_at >= :startDate 
                 AND created_at <= :endDate
                 AND status = :cancelledInquiryStatus) as cancelled_inquiries,
                
                -- Active carts (draft orders)
                (SELECT COUNT(*) 
                 FROM `order` 
                 WHERE created_at >= :startDate 
                 AND created_at <= :endDate
                 AND status = :draftStatus) as active_carts,
                
                -- Completed carts
                (SELECT COUNT(*) 
                 FROM `order` 
                 WHERE created_at >= :startDate 
                 AND created_at <= :endDate
                 AND status = :completedStatus) as completed_carts,
                
                -- Total shop revenue
                (SELECT COALESCE(SUM(total_amount), 0) 
                 FROM `order` 
                 WHERE created_at >= :startDate 
                 AND created_at <= :endDate
                 AND status NOT IN (:draftStatus, :cancelledStatus)) as total_shop_revenue,
                
                -- Cancelled orders revenue
                (SELECT COALESCE(SUM(total_amount), 0) 
                 FROM `order` 
                 WHERE created_at >= :startDate 
                 AND created_at <= :endDate
                 AND status = :cancelledStatus) as cancelled_orders_revenue
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'startDate' => $startDate->format('Y-m-d H:i:s'),
            'endDate' => $endDate->format('Y-m-d H:i:s'),
            'draftStatus' => Order::STATUS_DRAFT,
            'cancelledStatus' => Order::STATUS_CANCELED,
            'completedStatus' => Order::STATUS_COMPLETED,
            'submittedStatus' => Inquiry::STATUS_SUBMITTED,
            'inReviewStatus' => Inquiry::STATUS_IN_REVIEW,
            'moreInfoStatus' => Inquiry::STATUS_MORE_INFO,
            'infoProvidedStatus' => Inquiry::STATUS_INFORMATION_PROVIDED,
            'inProgressStatus' => Inquiry::STATUS_IN_PROGRESS,
            'cancelledInquiryStatus' => Inquiry::STATUS_CANCELED
        ])->fetchAssociative();

        return [
            'shopOrders' => (int) $result['shop_orders'],
            'manualInquiries' => (int) $result['manual_inquiries'],
            'activeInquiries' => (int) $result['active_inquiries'],
            'cancelledInquiries' => (int) $result['cancelled_inquiries'],
            'activeCarts' => (int) $result['active_carts'],
            'completedCarts' => (int) $result['completed_carts'],
            'totalShopRevenue' => (float) $result['total_shop_revenue'],
            'cancelledOrdersRevenue' => (float) $result['cancelled_orders_revenue']
        ];
    }

    private function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100.0 : 0.0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    #[Route('/v1/dashboard/inquiry-status-distribution', name: 'api_dashboard_inquiry_status_distribution', methods: ['GET'])]
    public function inquiryStatusDistribution(): JsonResponse
    {
        $data = $this->dashboardService->getInquiryStatusDistribution();
        return $this->json($data);
    }

    #[Route('/v1/dashboard/order-status-distribution', name: 'api_dashboard_order_status_distribution', methods: ['GET'])]
    public function orderStatusDistribution(): JsonResponse
    {
        $data = $this->dashboardService->getOrderStatusDistribution();
        return $this->json($data);
    }
}
