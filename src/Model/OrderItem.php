<?php

namespace App\Model;

/**
 * One line of an order: a product, how many of it, and its price per unit.
 */
final readonly class OrderItem
{
    /**
     * @param string $sku            Product identifier
     * @param int    $quantity       Number of units, at least 1
     * @param int    $unitPriceCents Price per unit in cents, never negative
     *
     * @throws \InvalidArgumentException when the SKU is empty, the quantity is below 1 or the price is negative
     */
    public function __construct(
        public string $sku,
        public int $quantity,
        public int $unitPriceCents,
    ) {
        if ('' === trim($sku)) {
            throw new \InvalidArgumentException('SKU must not be empty.');
        }
        if ($quantity < 1) {
            throw new \InvalidArgumentException(sprintf('Quantity for "%s" must be at least 1.', $sku));
        }
        if ($unitPriceCents < 0) {
            throw new \InvalidArgumentException(sprintf('Unit price for "%s" must not be negative.', $sku));
        }
    }

    /**
     * Returns the price of this line (quantity × unit price) in cents.
     */
    public function totalCents(): int
    {
        return $this->quantity * $this->unitPriceCents;
    }
}
