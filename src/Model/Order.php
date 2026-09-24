<?php

namespace App\Model;

/**
 * A placed order. Created by {@see \App\Service\OrderService::order()}.
 */
final readonly class Order
{
    /**
     * @param string             $id       Unique order identifier (32 hex characters)
     * @param Customer           $customer The customer who placed the order
     * @param list<OrderItem>    $items    The ordered products; at least one
     * @param \DateTimeImmutable $placedAt When the order was placed
     *
     * @throws \InvalidArgumentException when there are no items
     */
    public function __construct(
        public string $id,
        public Customer $customer,
        public array $items,
        public \DateTimeImmutable $placedAt,
    ) {
        if ([] === $items) {
            throw new \InvalidArgumentException('An order must contain at least one item.');
        }
    }

    /**
     * Returns the order total (the sum of all line totals) in cents.
     */
    public function totalCents(): int
    {
        return array_sum(array_map(static fn (OrderItem $item) => $item->totalCents(), $this->items));
    }
}
