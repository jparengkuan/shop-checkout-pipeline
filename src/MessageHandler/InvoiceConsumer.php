<?php

namespace App\MessageHandler;

use App\Message\OrderInvoiced;
use App\Message\OrderPlaced;
use App\Service\InvoiceWriter;
use App\Service\OrderEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Writes the invoice for each {@see OrderPlaced} message, then publishes {@see OrderInvoiced}.
 *
 * The confirmation email needs the invoice file, so {@see ConfirmationEmailConsumer} listens
 * for OrderInvoiced instead of OrderPlaced: it only runs once the file exists.
 * Runs inside the invoice-worker daemon (see .ddev/messenger-worker.sh).
 */
#[AsMessageHandler(fromTransport: 'order_invoices')]
final readonly class InvoiceConsumer
{
    /**
     * @param InvoiceWriter       $invoiceWriter Writes the invoice file
     * @param OrderEventPublisher $publisher     Publishes {@see OrderInvoiced}
     */
    public function __construct(
        private InvoiceWriter $invoiceWriter,
        private OrderEventPublisher $publisher,
    ) {
    }

    /**
     * Writes var/invoices/invoice-<order id>.txt and publishes {@see OrderInvoiced}.
     *
     * If publishing fails, Messenger retries the message; the retry overwrites the same
     * file, so it never leaves a duplicate invoice.
     *
     * @throws \RuntimeException when the invoice file cannot be written
     */
    public function __invoke(OrderPlaced $message): void
    {
        $path = $this->invoiceWriter->write($message->order);

        $this->publisher->publish(new OrderInvoiced($message->order, $path));
    }
}
