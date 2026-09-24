<?php

namespace App\MessageHandler;

use App\Message\OrderPlaced;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends the order confirmation email for each {@see OrderPlaced} message.
 *
 * Only handles messages read from the "order_emails" queue, so other consumers
 * of the "orders" exchange (e.g. invoicing) never trigger a duplicate email.
 * Runs inside the email-worker daemon (see .ddev/email-worker.sh).
 */
#[AsMessageHandler(fromTransport: 'order_emails')]
final readonly class EmailConsumer
{
    /**
     * @param MailerInterface $mailer Mailer used to send the confirmation
     * @param string          $from   Sender address, from the ORDER_EMAIL_FROM env var
     */
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(ORDER_EMAIL_FROM)%')]
        private string $from,
    ) {
    }

    /**
     * Emails the customer a plain-text summary of their order: each line item and the total.
     *
     * If sending fails the exception propagates, so Messenger retries the message
     * and finally moves it to the "failed" queue.
     *
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface when the email cannot be sent
     */
    public function __invoke(OrderPlaced $message): void
    {
        $order = $message->order;
        $customer = $order->customer;

        $lines = [];
        $total = 0;
        foreach ($order->items as $item) {
            $lines[] = sprintf(
                '%d x %s @ %s = %s',
                $item->quantity,
                $item->sku,
                self::money($item->unitPriceCents),
                self::money($item->totalCents()),
            );
            $total += $item->totalCents();
        }

        $body = implode("\n", [
            sprintf('Hi %s,', $customer->name),
            '',
            sprintf('Thank you for your order %s, placed on %s.', $order->id, $order->placedAt->format('Y-m-d H:i')),
            '',
            ...$lines,
            '',
            sprintf('Total: %s', self::money($total)),
        ]);

        $this->mailer->send((new Email())
            ->from($this->from)
            ->to(new Address($customer->email, $customer->name))
            ->subject(sprintf('Your order %s', $order->id))
            ->text($body));
    }

    /**
     * Formats an amount in cents as a decimal price, e.g. 1999 becomes "19.99".
     */
    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
