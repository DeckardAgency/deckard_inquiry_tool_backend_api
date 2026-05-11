<?php

declare(strict_types=1);

namespace App\Service\Pim;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sync products from the Deckard PIM into this app.
 *
 * Pull-only. PIM is the system of record for product master data; this app
 * mirrors the active channel's products into the local Product table so
 * orders/inquiries/admin views have stable local references.
 *
 * Important behaviour baked in from earlier production bugs in the B2B sync:
 *   - Skipped products still refresh pim_synced_at, otherwise the next run's
 *     orphan detection wrongly deletes them.
 *   - Orphan deletion uses re-queried batches via EntityManager::remove(),
 *     not DQL DELETE WHERE id IN (...) — DQL doesn't convert the
 *     binary-UUID parameter type from arrays and silently matches zero rows.
 *   - We skip orphan deletion when the first PIM page comes back empty so
 *     a transport failure can't wipe the local table.
 */
class ProductSyncService
{
    /**
     * Real make + model pairs the manual-entry car picker browses.
     *
     * The PIM dataset has random make/model combinations (Ford Megane,
     * Mercedes A4, …) which are not real cars. We deterministically
     * remap each product to one of these real pairs based on a hash of
     * its PIM id, so the same product always lands on the same vehicle.
     *
     * @var list<array{0:string,1:string}>
     */
    private const REAL_VEHICLES = [
        ['toyota', 'Corolla'], ['toyota', 'Yaris'], ['toyota', 'RAV4'],
        ['volkswagen', 'Golf'], ['volkswagen', 'Passat'], ['volkswagen', 'Polo'],
        ['mercedes', 'C-Class'], ['mercedes', 'E-Class'], ['mercedes', 'A-Class'],
        ['bmw', '3 Series'], ['bmw', '5 Series'], ['bmw', 'X3'],
        ['ford', 'Focus'], ['ford', 'Fiesta'], ['ford', 'Kuga'],
        ['renault', 'Megane'], ['renault', 'Clio'], ['renault', 'Captur'],
        ['skoda', 'Octavia'], ['skoda', 'Fabia'], ['skoda', 'Superb'],
        ['audi', 'A4'], ['audi', 'A3'], ['audi', 'A6'],
        ['opel', 'Astra'], ['opel', 'Corsa'], ['opel', 'Insignia'],
        ['peugeot', '308'], ['peugeot', '208'], ['peugeot', '3008'],
    ];

    /** @var array<string,string> Map of category code -> top-level ancestor code (or self if root). */
    private array $categoryRootByCode = [];

    public function __construct(
        private readonly PimClientInterface $pimClient,
        private readonly PimFeatureManager $featureManager,
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fullSync(): SyncResult
    {
        if (!$this->featureManager->isPimEnabled()) {
            return new SyncResult(errors: ['PIM integration is disabled']);
        }

        $syncStartedAt = new \DateTimeImmutable();
        $result = new SyncResult();
        $page = 1;
        $batchSize = $this->featureManager->getSyncBatchSize();
        $fetchFailed = false;

        // Build a child-code -> top-level-ancestor map from the PIM category tree
        // so each product's "module" (primary_category_code) is rolled up to one
        // of the 6 cars roots (cars_engine, cars_brakes, cars_suspension, …)
        // rather than a leaf like cars_belts or cars_lights.
        $this->categoryRootByCode = $this->buildCategoryRootMap();

        $this->logger->info('Starting full PIM sync');

        do {
            $products = $this->pimClient->getProducts([], $page, $batchSize);

            if ($products === [] && $page === 1) {
                $fetchFailed = true;
                break;
            }

            foreach ($products as $pimProduct) {
                try {
                    $this->syncProduct($pimProduct, $result);
                } catch (\Throwable $e) {
                    $sku = $pimProduct['sku'] ?? 'unknown';
                    $result->addError("Failed to sync {$sku}: {$e->getMessage()}");
                    $this->logger->error('Product sync failed', [
                        'sku' => $sku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->em->flush();
            $this->em->clear();
            $page++;
        } while (count($products) === $batchSize);

        if (!$fetchFailed) {
            $this->deleteOrphans($syncStartedAt, $result);
        }

        $this->logger->info('Full PIM sync completed', $result->toArray());

        return $result;
    }

    public function incrementalSync(\DateTimeInterface $since): SyncResult
    {
        if (!$this->featureManager->isPimEnabled()) {
            return new SyncResult(errors: ['PIM integration is disabled']);
        }

        $result = new SyncResult();
        $this->logger->info('Starting incremental PIM sync', ['since' => $since->format('c')]);

        $products = $this->pimClient->getProductsModifiedSince($since);
        foreach ($products as $pimProduct) {
            try {
                $this->syncProduct($pimProduct, $result);
            } catch (\Throwable $e) {
                $sku = $pimProduct['sku'] ?? 'unknown';
                $result->addError("Failed to sync {$sku}: {$e->getMessage()}");
            }
        }
        $this->em->flush();

        return $result;
    }

    /**
     * @param array<string, mixed> $pimData
     */
    public function syncProduct(array $pimData, ?SyncResult $result = null): Product
    {
        $sku = $pimData['sku'] ?? throw new \InvalidArgumentException('PIM product is missing sku');
        $pimId = $pimData['id'] ?? null;

        // Stable PIM identifier first. Fall back to matching by sku against the local
        // partNo for the *first* sync when nothing has a pim_id yet — this lets an
        // existing local row get adopted as PIM-managed instead of duplicated.
        $product = ($pimId !== null ? $this->productRepository->findOneBy(['pimId' => $pimId]) : null)
            ?? $this->productRepository->findOneBy(['partNo' => $sku])
            ?? new Product();
        $isNew = $product->getId() === null;

        if ($isNew) {
            $product->setPartNo($sku);
        }

        $dataHash = $this->calculateHash($pimData);
        if (!$isNew && $product->getPimDataHash() === $dataHash) {
            // Touch pim_synced_at so orphan detection knows this product is
            // still present in PIM, even though its data hasn't changed.
            $product->markAsSynced();
            $this->em->persist($product);
            $result?->addSkipped();
            return $product;
        }

        $this->mapPimDataToProduct($pimData, $product);

        $product->setIsFromPim(true);
        $product->setPimId($pimData['id'] ?? null);
        $product->setPimDataHash($dataHash);
        $product->markAsSynced();

        $this->em->persist($product);

        if ($isNew) {
            $result?->addCreated();
        } else {
            $result?->addUpdated();
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $pimData
     */
    private function mapPimDataToProduct(array $pimData, Product $product): void
    {
        $name = $this->familyAware($pimData, 'name');
        $product->setName(is_scalar($name) ? (string) $name : ($pimData['sku'] ?? ''));

        $partNumber = $this->familyAware($pimData, 'part_number');
        $product->setPartNo(is_scalar($partNumber) ? (string) $partNumber : ($pimData['sku'] ?? ''));

        $description = $this->familyAware($pimData, 'description');
        $product->setShortDescription(is_scalar($description) ? (string) $description : null);

        $product->setPrice($this->extractPrice($this->familyAware($pimData, 'price')));

        $weight = $this->familyAware($pimData, 'weight_kg') ?? $this->familyAware($pimData, 'weight');
        $product->setWeight(is_scalar($weight) ? (string) $weight : null);

        $unit = $this->familyAware($pimData, 'unit');
        if (is_scalar($unit) && method_exists($product, 'setUnit')) {
            $product->setUnit((string) $unit);
        }

        // Vehicle metadata used by the manual-entry car/module navigation.
        // PIM ships random (make, model) pairings (Ford Megane, Mercedes A4, …)
        // so we deterministically remap every product to a real vehicle based
        // on a hash of its PIM id. Year range gets normalised at the same
        // time since some PIM rows have year_from > year_to.
        $seed = (string) ($pimData['id'] ?? $pimData['sku'] ?? '');
        [$make, $model] = $this->pickRealVehicle($seed);
        $product->setVehicleMake($make);
        $product->setVehicleModel($model);

        $yearFromRaw = $this->familyAware($pimData, 'year_from');
        $yearToRaw   = $this->familyAware($pimData, 'year_to');
        $yearFromInt = is_numeric($yearFromRaw) ? (int) $yearFromRaw : null;
        $yearToInt   = is_numeric($yearToRaw) ? (int) $yearToRaw : null;
        if ($yearFromInt !== null && $yearToInt !== null && $yearFromInt > $yearToInt) {
            [$yearFromInt, $yearToInt] = [$yearToInt, $yearFromInt];
        }
        $product->setYearFrom($yearFromInt !== null ? (string) $yearFromInt : null);
        $product->setYearTo($yearToInt !== null ? (string) $yearToInt : null);

        // First category, rolled up to its top-level ancestor in the PIM tree.
        // This makes "module" = engine/brakes/suspension/etc. consistently.
        $categories = $pimData['categories'] ?? [];
        $primary = is_array($categories) && $categories !== []
            ? (is_string($categories[0]) ? $categories[0] : null)
            : null;
        if ($primary !== null && isset($this->categoryRootByCode[$primary])) {
            $primary = $this->categoryRootByCode[$primary];
        }
        $product->setPrimaryCategoryCode($primary);
    }

    /**
     * Pick a real (make, model) pair deterministically from REAL_VEHICLES,
     * keyed on the product's PIM id so the same product always lands on the
     * same vehicle even across re-syncs.
     *
     * @return array{0:string,1:string}
     */
    private function pickRealVehicle(string $seed): array
    {
        if ($seed === '') {
            return self::REAL_VEHICLES[0];
        }
        $index = crc32($seed) % count(self::REAL_VEHICLES);
        return self::REAL_VEHICLES[$index];
    }

    /**
     * Fetch the PIM category tree for the active channel and return a flat map
     * from each category code to its top-level ancestor. Falls back to identity
     * if PIM is unreachable or returns nothing.
     *
     * @return array<string,string>
     */
    private function buildCategoryRootMap(): array
    {
        try {
            $categories = $this->pimClient->getCategories();
        } catch (\Throwable $e) {
            $this->logger->warning('PIM category fetch failed; modules will use raw codes', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        // First pass: code -> parent code (null for roots).
        $parentOf = [];
        foreach ($categories as $cat) {
            $code = $cat['code'] ?? null;
            if (!is_string($code) || $code === '') {
                continue;
            }
            $parent = $cat['parent'] ?? null;
            $parentOf[$code] = is_string($parent) && $parent !== '' ? $parent : null;
        }

        // Second pass: walk up to the root for each code.
        $rootOf = [];
        foreach ($parentOf as $code => $_) {
            $current = $code;
            $seen = [];
            while (isset($parentOf[$current]) && $parentOf[$current] !== null) {
                if (isset($seen[$current])) {
                    break; // cycle guard
                }
                $seen[$current] = true;
                $current = $parentOf[$current];
            }
            $rootOf[$code] = $current;
        }

        return $rootOf;
    }

    /**
     * Delete PIM-sourced products whose pim_synced_at predates this sync —
     * they exist locally but weren't returned by PIM for the active channel.
     */
    private function deleteOrphans(\DateTimeInterface $syncStartedAt, SyncResult $result): void
    {
        $totalDeleted = 0;
        while (true) {
            $orphans = $this->productRepository->createQueryBuilder('p')
                ->where('p.isFromPim = :true')
                ->andWhere('p.pimSyncedAt IS NULL OR p.pimSyncedAt < :since')
                ->setParameter('true', true)
                ->setParameter('since', $syncStartedAt)
                ->setMaxResults(100)
                ->getQuery()
                ->getResult();

            if ($orphans === []) {
                break;
            }

            foreach ($orphans as $orphan) {
                $this->em->remove($orphan);
                $result->addDeleted();
                $totalDeleted++;
            }
            $this->em->flush();
            $this->em->clear();
        }

        if ($totalDeleted > 0) {
            $this->logger->info(sprintf('Deleted %d orphaned PIM products', $totalDeleted));
        }
    }

    /**
     * Resolve a PIM attribute value taking the family prefix into account.
     * Tries '{prefix}_{key}' (prefix = family with '_family' stripped),
     * then '{family}_{key}', then the bare key.
     *
     * @param array<string, mixed> $pimData
     */
    private function familyAware(array $pimData, string $key): mixed
    {
        $values = $pimData['values'] ?? [];
        $family = $pimData['family'] ?? '';

        if (is_string($family) && $family !== '') {
            $prefix = preg_replace('/_family$/', '', $family);
            if (is_string($prefix) && $prefix !== '' && $prefix !== $family) {
                $v = $this->extractValue($values[$prefix . '_' . $key] ?? null);
                if ($v !== null) {
                    return $v;
                }
            }
            $v2 = $this->extractValue($values[$family . '_' . $key] ?? null);
            if ($v2 !== null) {
                return $v2;
            }
        }

        return $this->extractValue($values[$key] ?? null);
    }

    /**
     * Unwrap a PIM attribute value: localized maps -> first English/German variant,
     * reference values like {"code": "..."} -> the code, primitives/arrays -> as-is.
     */
    private function extractValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return $value;
        }
        if (is_array($value)) {
            if (isset($value['code']) && (is_string($value['code']) || is_numeric($value['code']))) {
                return $value['code'];
            }
            foreach (['en_US', 'en_GB', 'en', 'de_DE', 'default'] as $locale) {
                if (array_key_exists($locale, $value)) {
                    return $value[$locale];
                }
            }
            $first = reset($value);
            return $first === false ? null : $first;
        }
        return null;
    }

    private function extractPrice(mixed $price): float
    {
        if ($price === null) {
            return 0.0;
        }
        if (is_numeric($price)) {
            return (float) $price;
        }
        if (is_array($price)) {
            foreach (['EUR', 'USD', 'GBP'] as $currency) {
                if (isset($price[$currency]) && is_numeric($price[$currency])) {
                    return (float) $price[$currency];
                }
            }
            $first = reset($price);
            return is_numeric($first) ? (float) $first : 0.0;
        }
        return 0.0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function calculateHash(array $data): string
    {
        unset($data['updated_at'], $data['created_at']);
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
