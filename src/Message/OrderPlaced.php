<?php

namespace App\Message;

use App\Model\Order;

/**
 * Published when an order is placed (routing key "order.placed").
 *
 * Consumed by {@see \App\MessageHandler\InvoiceConsumer}; the confirmation email
 * follows later, on {@see OrderInvoiced}.
 */
final readonly class OrderPlaced implements OrderEvent
{
    public const ROUTING_KEY = 'order.placed';

    /**
     * @param Order $order The order that was placed
     */
    public function __construct(
        public Order $order,
    ) {
    }

    public function routingKey(): string
    {
        return self::ROUTING_KEY;
    }
}
