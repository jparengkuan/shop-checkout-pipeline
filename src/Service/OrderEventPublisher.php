<?php

namespace App\Service;

use App\Message\OrderEvent;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Publishes {@see OrderEvent}s to the "orders" exchange with their routing key.
 *
 * Without the routing key no queue binding matches and RabbitMQ silently drops the
 * message, so every order event goes through here instead of the bus directly.
 */
final readonly class OrderEventPublisher
{
    /**
     * @param MessageBusInterface $bus Bus that sends the event to the "orders" transport
     */
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Publishes the event with routing key {@see OrderEvent::routingKey()}.
     */
    public function publish(OrderEvent $event): void
    {
        $this->bus->dispatch($event, [new AmqpStamp($event->routingKey())]);
    }
}
