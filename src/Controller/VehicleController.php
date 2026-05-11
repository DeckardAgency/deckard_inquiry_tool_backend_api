<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Vehicle endpoints derived from PIM-sourced products.
 *
 * The manual-entry routes in the client app browse "cars" (make + model)
 * before drilling into modules and parts. The /products table already
 * carries vehicle_make / vehicle_model / primary_category_code on every
 * PIM-sourced row, so these endpoints just aggregate that into the shape
 * the UI wants.
 */
#[Route('/api/v1/vehicles', name: 'app_vehicles_')]
class VehicleController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * List vehicles as distinct (make, model) groups.
     *
     * @return JsonResponse{vehicles:list<array{id:string,make:string,model:string,partCount:int,yearFrom:?string,yearTo:?string,modules:list<string>}>}
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $rows = $this->productRepository->createQueryBuilder('p')
            ->select(
                'p.vehicleMake AS make',
                'p.vehicleModel AS model',
                'COUNT(p.id) AS partCount',
                'MIN(p.yearFrom) AS yearFromMin',
                'MAX(p.yearTo) AS yearToMax'
            )
            ->where('p.vehicleMake IS NOT NULL')
            ->andWhere('p.vehicleModel IS NOT NULL')
            ->andWhere('p.isFromPim = true')
            ->groupBy('p.vehicleMake', 'p.vehicleModel')
            ->orderBy('partCount', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getArrayResult();

        $vehicles = array_map(function (array $row) {
            $make = (string) $row['make'];
            $model = (string) $row['model'];
            return [
                'id' => $this->vehicleId($make, $model),
                'make' => $make,
                'model' => $model,
                'partCount' => (int) $row['partCount'],
                'yearFrom' => $row['yearFromMin'] ?? null,
                'yearTo' => $row['yearToMax'] ?? null,
                'modules' => $this->modulesForVehicle($make, $model),
            ];
        }, $rows);

        return new JsonResponse([
            'totalItems' => count($vehicles),
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Modules (top-level part categories) available for a given vehicle.
     */
    #[Route('/{make}/{model}/modules', name: 'modules', methods: ['GET'])]
    public function modules(string $make, string $model): JsonResponse
    {
        $modules = $this->modulesForVehicle($make, $model);
        return new JsonResponse([
            'vehicle' => ['make' => $make, 'model' => $model],
            'totalItems' => count($modules),
            'modules' => $modules,
        ]);
    }

    /**
     * @return list<array{code:string,label:string,partCount:int}>
     */
    private function modulesForVehicle(string $make, string $model): array
    {
        $rows = $this->productRepository->createQueryBuilder('p')
            ->select('p.primaryCategoryCode AS code', 'COUNT(p.id) AS partCount')
            ->where('p.vehicleMake = :make')
            ->andWhere('p.vehicleModel = :model')
            ->andWhere('p.primaryCategoryCode IS NOT NULL')
            ->andWhere('p.isFromPim = true')
            ->setParameter('make', $make)
            ->setParameter('model', $model)
            ->groupBy('p.primaryCategoryCode')
            ->orderBy('partCount', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            $code = (string) $row['code'];
            return [
                'code' => $code,
                'label' => self::moduleLabel($code),
                'partCount' => (int) $row['partCount'],
            ];
        }, $rows);
    }

    private function vehicleId(string $make, string $model): string
    {
        return strtolower($make) . ':' . strtolower(str_replace(' ', '-', $model));
    }

    private static function moduleLabel(string $code): string
    {
        // Strip the family prefix (cars_engine -> engine) and title-case it for the UI.
        $bare = preg_replace('/^[a-z]+_/', '', $code) ?? $code;
        return ucwords(str_replace('_', ' ', $bare));
    }
}
