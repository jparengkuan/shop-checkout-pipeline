<?php

namespace App\Message;

/**
 * A message about an order, published to the "orders" topic exchange on RabbitMQ.
 *
 * Publish it with {@see \App\Service\OrderEventPublisher}, which sends it with
 * {@see self::routingKey()}; each queue receives the events whose key it is bound to
 * (see config/packages/messenger.yaml).
 */
interface OrderEvent
{
    /**
     * Returns the routing key on the "orders" exchange, e.g. "order.placed".
     */
    public function routingKey(): string;
}
