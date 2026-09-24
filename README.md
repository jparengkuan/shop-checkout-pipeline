# Shop Checkout Pipeline

[![Lint](https://github.com/jparengkuan/shop-checkout-pipeline/actions/workflows/lint.yml/badge.svg)](https://github.com/jparengkuan/shop-checkout-pipeline/actions/workflows/lint.yml)

A small Symfony 7.4 application that demonstrates an event-driven checkout flow with
**Symfony Messenger** and **RabbitMQ**. Placing an order publishes an `OrderPlaced`
message to a topic exchange; a background worker consumes it and emails the customer
an order confirmation.

## How it works

```
OrderService::order()
        │  dispatch OrderPlaced (routing key: order.placed)
        ▼
┌──────────────────────┐        ┌──────────────────────┐
│ "orders" exchange    │───────▶│ "order_emails" queue │
│ (topic)              │        └──────────┬───────────┘
└──────────────────────┘                   │ messenger:consume order_emails
                                           ▼
                                 EmailConsumer ──▶ Mailer (Mailpit)
                                           │
                                           │ fails after retries
                                           ▼
                                 "failed" queue
```

- **`App\Service\OrderService`** creates an `Order` and dispatches an `OrderPlaced`
  message with the `order.placed` routing key.
- **`orders` transport** publishes to the `orders` topic exchange and declares every
  consumer queue up front, so no message is lost if a worker hasn't started yet.
- **`order_emails` transport** is the consume side: one queue per consumer, so new
  consumers (e.g. invoicing) can bind to the same exchange without duplicate emails.
- **`App\MessageHandler\EmailConsumer`** handles messages from `order_emails` only and
  sends a plain-text summary with line items and total.
- **`failed` transport** holds messages that still fail after all retries.

## Project structure

```
src/
├── Command/GenerateRandomOrderCommand.php   # app:order:random — places test orders
├── Message/OrderPlaced.php                  # Message published per order
├── MessageHandler/EmailConsumer.php         # Sends the confirmation email
├── Model/                                   # Customer, Order, OrderItem (readonly value objects)
└── Service/OrderService.php                 # Places orders and publishes OrderPlaced
config/packages/messenger.yaml               # Exchange, queues, routing, failure transport
.ddev/                                       # DDEV config, RabbitMQ service, email worker daemon
```

## Requirements

- [DDEV](https://ddev.com/) (recommended), which provides PHP 8.3, RabbitMQ, Mailpit and
  the email worker out of the box
- Or, without DDEV: PHP ≥ 8.2 with `ext-amqp`, Composer 2, a RabbitMQ server and an SMTP
  server such as Mailpit

## Getting started (DDEV)

```bash
git clone https://github.com/jparengkuan/shop-checkout-pipeline.git
cd shop-checkout-pipeline
ddev start
ddev composer install
```

`ddev start` also starts the `email-worker` daemon (`.ddev/email-worker.sh`), which waits
for RabbitMQ and then runs `messenger:consume order_emails`. It restarts hourly or at
128 MB to pick up fresh code and memory.

### Place some orders

```bash
ddev exec bin/console app:order:random --count=5
```

Each order goes through RabbitMQ like a real one, and the worker sends the confirmation.

### Inspect the results

| What              | Where                                                   |
|-------------------|---------------------------------------------------------|
| Sent emails       | `ddev mailpit` (opens the Mailpit UI)                   |
| RabbitMQ UI       | https://shop-checkout-pipeline.ddev.site:15673 (`guest` / `guest`) |
| Worker logs       | `ddev logs -s web`                                      |
| Failed messages   | `ddev exec bin/console messenger:failed:show`           |
| Retry failed      | `ddev exec bin/console messenger:failed:retry`          |

## Configuration

Environment variables (see `.env`; override locally in `.env.local`):

| Variable                  | Default                                      | Purpose                            |
|---------------------------|----------------------------------------------|------------------------------------|
| `MESSENGER_TRANSPORT_DSN` | `amqp://guest:guest@rabbitmq:5672/%2f`        | RabbitMQ connection                |
| `MAILER_DSN`              | `null://null`                                | Mail transport                     |
| `ORDER_EMAIL_FROM`        | `shop@example.com`                           | Sender address of order emails     |

## Code quality

The project uses PHP_CodeSniffer (PSR-12, `phpcs.xml.dist`) and PHPStan (level 5 with the
Symfony extension, `phpstan.dist.neon`).

```bash
ddev composer lint   # phpcs + phpstan
ddev composer fix    # auto-fix coding standard issues with phpcbf
```

Both checks run on every push and pull request via GitHub Actions
(`.github/workflows/lint.yml`).

## License

Released under the MIT License. See [LICENSE](LICENSE).
