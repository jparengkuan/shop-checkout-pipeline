<?php

namespace App\Mailer;

use App\Model\Customer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends plain-text emails from the shop to a customer.
 */
final readonly class CustomerMailer
{
    /**
     * @param MailerInterface $mailer Mailer used to send the email
     * @param string          $from   Sender address, from the ORDER_EMAIL_FROM env var
     */
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(ORDER_EMAIL_FROM)%')]
        private string $from,
    ) {
    }

    /**
     * Sends a plain-text email to the customer, optionally with a text file attached.
     *
     * @param list<string> $lines          Body, one entry per line
     * @param string|null  $attachmentPath Path of a text file to attach, if any
     *
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface when the email cannot be sent
     */
    public function send(Customer $customer, string $subject, array $lines, ?string $attachmentPath = null): void
    {
        $email = (new Email())
            ->from($this->from)
            ->to(new Address($customer->email, $customer->name))
            ->subject($subject)
            ->text(implode("\n", $lines));

        if (null !== $attachmentPath) {
            $email->attachFromPath($attachmentPath, basename($attachmentPath), 'text/plain');
        }

        $this->mailer->send($email);
    }
}
