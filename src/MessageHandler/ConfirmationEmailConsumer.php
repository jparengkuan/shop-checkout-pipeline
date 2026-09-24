<?php

namespace App\MessageHandler;

use App\Mailer\CustomerMailer;
use App\Message\OrderInvoiced;
use App\Model\Money;
use App\Model\OrderItem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends the order confirmation email, with the invoice attached, for each {@see OrderInvoiced} message.
 *
 * Listens for OrderInvoiced rather than OrderPlaced because it needs the invoice file:
 * {@see InvoiceConsumer} publishes OrderInvoiced only after it wrote that file.
 * Runs inside the email-worker daemon (see .ddev/messenger-worker.sh).
 */
#[AsMessageHandler(fromTransport: 'order_emails')]
final readonly class ConfirmationEmailConsumer
{
    /**
     * @param CustomerMailer $mailer Sends the confirmation to the customer
     */
    public function __construct(
        private CustomerMailer $mailer,
    ) {
    }

    /**
     * Emails the customer a summary of their order (each line item and the total)
     * with the invoice attached.
     *
     * If sending fails the exception propagates, so Messenger retries the message
     * and finally moves it to the "failed" queue.
     *
     * @throws \RuntimeException when the invoice file does not exist
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface when the email cannot be sent
     */
    public function __invoke(OrderInvoiced $message): void
    {
        $order = $message->order;
        if (!is_file($message->invoicePath)) {
            throw new \RuntimeException(sprintf(
                'Invoice "%s" for order %s does not exist.',
                $message->invoicePath,
                $order->id,
            ));
        }

        $itemLines = array_map(
            static fn (OrderItem $item) => sprintf(
                '%d x %s @ %s = %s',
                $item->quantity,
                $item->sku,
                Money::format($item->unitPriceCents),
                Money::format($item->totalCents()),
            ),
            $order->items,
        );

        $placedAt = $order->placedAt->format('Y-m-d H:i');

        $this->mailer->send(
            $order->customer,
            sprintf('Your order %s', $order->id),
            [
                sprintf('Hi %s,', $order->customer->name),
                '',
                sprintf('Thank you for your order %s, placed on %s.', $order->id, $placedAt),
                '',
                ...$itemLines,
                '',
                sprintf('Total: %s', Money::format($order->totalCents())),
                '',
                'Your invoice is attached.',
            ],
            $message->invoicePath,
        );
    }
}
