<?php

namespace App\Model;

/**
 * A placed order. Created by {@see \App\Service\OrderService::order()}.
 */
final readonly class Order
{
    /**
     * @param string                   $id       Unique order identifier (32 hex characters)
     * @param Customer                 $customer The customer who placed the order
     * @param non-empty-list<OrderItem> $items   The ordered products
     * @param \DateTimeImmutable       $placedAt When the order was placed
     */
    public function __construct(
        public string $id,
        public Customer $customer,
        public array $items,
        public \DateTimeImmutable $placedAt,
    ) {
    }
}
