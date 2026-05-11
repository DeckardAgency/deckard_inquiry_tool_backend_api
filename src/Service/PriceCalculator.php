<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Product;
use App\Entity\Order;
use App\Entity\ClientProductPrice;

class PriceCalculator
{
    /**
     * Get client-specific price for a product
     */
    public function getClientProductPrice(Client $client, Product $product): ?ClientProductPrice
    {
        foreach ($client->getProductPrices() as $clientProductPrice) {
            // Compare UUIDs as strings to avoid comparison issues
            if ($clientProductPrice->getProduct()->getId()->toRfc4122() === $product->getId()->toRfc4122()
                && $clientProductPrice->isValid()) {
                return $clientProductPrice;
            }
        }

        return null;
    }

    /**
     * Calculate total order amount based on client pricing
     */
    public function calculateOrderTotal(Order $order): float
    {
        $totalAmount = 0;
        $user = $order->getUser();
        $client = $user ? $user->getClient() : null;

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (!$product) {
                $totalAmount += $item->getSubtotal();
                continue;
            }

            $unitPrice = $item->getUnitPrice();
            $quantity = $item->getQuantity();

            // If there's a client and the price hasn't been set yet, calculate it
            if ($client && $unitPrice === 0) {
                $clientProductPrice = $this->getClientProductPrice($client, $product);
                if ($clientProductPrice && $clientProductPrice->isValid()) {
                    $unitPrice = $clientProductPrice->getEffectivePrice();
                } else {
                    // Fall back to standard product price
                    $unitPrice = $product->getPrice();
                }
            }

            $totalAmount += ($unitPrice * $quantity);
        }

        return $totalAmount;
    }

    /**
     * Get detailed order items with proper pricing
     */
    public function getOrderItemsDetails(Order $order): array
    {
        $items = [];
        $user = $order->getUser();
        $client = $user ? $user->getClient() : null;

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (!$product) {
                $items[] = [
                    'name' => 'Unknown Product',
                    'quantity' => $item->getQuantity(),
                    'unitPrice' => $item->getUnitPrice(),
                    'subtotal' => $item->getSubtotal(),
                    'isCustomPrice' => false
                ];
                continue;
            }

            $unitPrice = $item->getUnitPrice();
            $isCustomPrice = $item->isCustomPrice();

            // If price is 0, recalculate
            if ($unitPrice === 0 && $client) {
                $clientProductPrice = $this->getClientProductPrice($client, $product);
                if ($clientProductPrice && $clientProductPrice->isValid()) {
                    $unitPrice = $clientProductPrice->getEffectivePrice();
                    $isCustomPrice = true;
                } else {
                    $unitPrice = $product->getPrice();
                    $isCustomPrice = false;
                }
            } elseif ($unitPrice === 0) {
                // No client, use standard price
                $unitPrice = $product->getPrice();
                $isCustomPrice = false;
            }

            $subtotal = $unitPrice * $item->getQuantity();

            $items[] = [
                'name' => $product->getName(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $unitPrice,
                'subtotal' => $subtotal,
                'isCustomPrice' => $isCustomPrice
            ];
        }

        return $items;
    }

    /**
     * Format item list for email display
     */
    public function formatItemListHtml(array $items, bool $showCustomPriceInfo = true): string
    {
        $itemsList = '';
        foreach ($items as $item) {
            $itemsList .= sprintf(
                '%s x %d @ %s%s = %s<br>',
                htmlspecialchars($item['name']),
                $item['quantity'],
                number_format($item['unitPrice'], 2),
                ($showCustomPriceInfo && $item['isCustomPrice']) ? ' (Custom Price)' : '',
                number_format($item['subtotal'], 2)
            );
        }

        return $itemsList;
    }
}
