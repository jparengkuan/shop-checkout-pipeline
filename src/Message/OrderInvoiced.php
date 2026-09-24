<?php

namespace App\Message;

use App\Model\Order;

/**
 * Published once the invoice file for an order exists (routing key "order.invoiced").
 *
 * Sent by {@see \App\MessageHandler\InvoiceConsumer} after it wrote the invoice, so
 * {@see \App\MessageHandler\ConfirmationEmailConsumer} can attach it to the confirmation.
 */
final readonly class OrderInvoiced implements OrderEvent
{
    public const ROUTING_KEY = 'order.invoiced';

    /**
     * @param Order  $order       The order the invoice is for
     * @param string $invoicePath Absolute path of the invoice file
     */
    public function __construct(
        public Order $order,
        public string $invoicePath,
    ) {
    }

    public function routingKey(): string
    {
        return self::ROUTING_KEY;
    }
}
