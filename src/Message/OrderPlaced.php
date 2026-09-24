<?php

namespace App\Message;

use App\Model\Order;

/**
 * Published to the "orders" exchange on RabbitMQ whenever an order is placed.
 *
 * Every queue bound to {@see self::ROUTING_KEY} receives its own copy; currently
 * only "order_emails", consumed by {@see \App\MessageHandler\EmailConsumer}.
 */
final readonly class OrderPlaced
{
    /** Routing key on the "orders" exchange; queues bind to it to receive this message. */
    public const ROUTING_KEY = 'order.placed';

    /**
     * @param Order $order The order that was placed
     */
    public function __construct(
        public Order $order,
    ) {
    }
}
