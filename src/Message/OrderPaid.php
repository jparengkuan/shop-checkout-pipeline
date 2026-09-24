<?php

namespace App\Message;

use App\Model\Order;

/**
 * Published when the customer has paid for an order (routing key "order.paid").
 *
 * Consumed by {@see \App\MessageHandler\PaymentEmailConsumer}.
 */
final readonly class OrderPaid implements OrderEvent
{
    public const ROUTING_KEY = 'order.paid';

    /**
     * @param Order              $order  The order that was paid for
     * @param \DateTimeImmutable $paidAt When the payment was received
     */
    public function __construct(
        public Order $order,
        public \DateTimeImmutable $paidAt,
    ) {
    }

    public function routingKey(): string
    {
        return self::ROUTING_KEY;
    }
}
