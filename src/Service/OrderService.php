<?php

namespace App\Service;

use App\Message\OrderPaid;
use App\Message\OrderPlaced;
use App\Model\Customer;
use App\Model\Order;
use App\Model\OrderItem;

/**
 * Places orders, records payments and announces both to the rest of the system via RabbitMQ.
 */
final readonly class OrderService
{
    /**
     * @param OrderEventPublisher $publisher Publishes {@see OrderPlaced} and {@see OrderPaid}
     */
    public function __construct(
        private OrderEventPublisher $publisher,
    ) {
    }

    /**
     * Places an order for the given customer and publishes {@see OrderPlaced}.
     *
     * @param Customer  $customer The customer placing the order
     * @param OrderItem ...$items The ordered products; at least one
     *
     * @return Order The placed order
     *
     * @throws \InvalidArgumentException when no items are given
     */
    public function order(Customer $customer, OrderItem ...$items): Order
    {
        $order = new Order(
            id: bin2hex(random_bytes(16)),
            customer: $customer,
            items: array_values($items),
            placedAt: new \DateTimeImmutable(),
        );

        $this->publisher->publish(new OrderPlaced($order));

        return $order;
    }

    /**
     * Records that the order was paid and publishes {@see OrderPaid}.
     *
     * @param Order $order The order that was paid for
     */
    public function markPaid(Order $order): void
    {
        $this->publisher->publish(new OrderPaid($order, new \DateTimeImmutable()));
    }
}
