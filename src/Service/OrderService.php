<?php

namespace App\Service;

use App\Message\OrderPlaced;
use App\Model\Customer;
use App\Model\Order;
use App\Model\OrderItem;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Places orders and announces them to the rest of the system via RabbitMQ.
 */
class OrderService
{
    /**
     * @param MessageBusInterface $bus Bus used to publish {@see OrderPlaced}
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Places an order for the given customer and publishes an {@see OrderPlaced} message.
     *
     * The message is sent with routing key {@see OrderPlaced::ROUTING_KEY}, so every
     * queue bound to it (such as "order_emails") receives the order.
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
        if ([] === $items) {
            throw new \InvalidArgumentException('An order must contain at least one item.');
        }

        $order = new Order(
            id: bin2hex(random_bytes(16)),
            customer: $customer,
            items: array_values($items),
            placedAt: new \DateTimeImmutable(),
        );

        $this->bus->dispatch(new OrderPlaced($order), [new AmqpStamp(OrderPlaced::ROUTING_KEY)]);

        return $order;
    }
}
