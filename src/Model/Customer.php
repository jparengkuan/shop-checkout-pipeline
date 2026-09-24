<?php

namespace App\Model;

/**
 * The customer who places an order and receives its confirmation email.
 */
final readonly class Customer
{
    /**
     * @param string $id    Unique customer identifier
     * @param string $name  Full name, used to address the customer in emails
     * @param string $email Address the order confirmation is sent to
     *
     * @throws \InvalidArgumentException when the ID or name is empty, or the email is invalid
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {
        if ('' === trim($id)) {
            throw new \InvalidArgumentException('Customer ID must not be empty.');
        }
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Customer name must not be empty.');
        }
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $email));
        }
    }
}
