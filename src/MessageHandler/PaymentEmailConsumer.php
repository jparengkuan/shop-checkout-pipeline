<?php

namespace App\MessageHandler;

use App\Mailer\CustomerMailer;
use App\Message\OrderPaid;
use App\Model\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends the "payment received" email for each {@see OrderPaid} message.
 *
 * Reads from the same "order_emails" queue as {@see ConfirmationEmailConsumer}, so the
 * email-worker handles it without an extra daemon.
 */
#[AsMessageHandler(fromTransport: 'order_emails')]
final readonly class PaymentEmailConsumer
{
    /**
     * @param CustomerMailer $mailer Sends the email to the customer
     */
    public function __construct(
        private CustomerMailer $mailer,
    ) {
    }

    /**
     * Emails the customer that the payment for their order was received.
     *
     * If sending fails the exception propagates, so Messenger retries the message
     * and finally moves it to the "failed" queue.
     *
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface when the email cannot be sent
     */
    public function __invoke(OrderPaid $message): void
    {
        $order = $message->order;

        $this->mailer->send(
            $order->customer,
            sprintf('Payment received for order %s', $order->id),
            [
                sprintf('Hi %s,', $order->customer->name),
                '',
                sprintf(
                    'We received your payment of %s for order %s on %s.',
                    Money::format($order->totalCents()),
                    $order->id,
                    $message->paidAt->format('Y-m-d H:i'),
                ),
                'We will start packing your order now.',
            ],
        );
    }
}
