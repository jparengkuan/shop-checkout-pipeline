# Shop Checkout Pipeline

[![Lint](https://github.com/jparengkuan/shop-checkout-pipeline/actions/workflows/lint.yml/badge.svg)](https://github.com/jparengkuan/shop-checkout-pipeline/actions/workflows/lint.yml)

An event-driven checkout pipeline built with **Symfony 7.4**, **Symfony Messenger** and
**RabbitMQ**. Each step of an order is an event on a topic exchange, and independent
workers react to it:

1. An order is placed → the **invoice-worker** writes a plain-text invoice.
2. The invoice exists → the **email-worker** sends the order confirmation with the invoice attached.
3. The order is paid → the **email-worker** sends a "payment received" email.

## Architecture

### Events and routing

All events are published to one **topic exchange**, `orders`. Each event carries a
**routing key**, and each queue receives only the keys it is bound to.

```
 PUBLISHER                        EVENT (routing key)                QUEUE               HANDLER
 ─────────                        ───────────────────                ─────               ───────
 OrderService::order()     ──▶  OrderPlaced   (order.placed)    ──▶  order_invoices  ──▶ InvoiceConsumer
                                                                                            │ 1. writes invoice-<id>.txt
                                                                                            │ 2. publishes ▼
 InvoiceConsumer           ──▶  OrderInvoiced (order.invoiced)  ──▶  order_emails    ──▶ ConfirmationEmailConsumer
                                                                                         (confirmation + invoice)
 OrderService::markPaid()  ──▶  OrderPaid     (order.paid)      ──▶  order_emails    ──▶ PaymentEmailConsumer
                                                                                         ("payment received")
```

| Routing key      | Event           | Published by               | Queue            | Handler                     | Worker           |
|------------------|-----------------|----------------------------|------------------|-----------------------------|------------------|
| `order.placed`   | `OrderPlaced`   | `OrderService::order()`    | `order_invoices` | `InvoiceConsumer`           | `invoice-worker` |
| `order.invoiced` | `OrderInvoiced` | `InvoiceConsumer`          | `order_emails`   | `ConfirmationEmailConsumer` | `email-worker`   |
| `order.paid`     | `OrderPaid`     | `OrderService::markPaid()` | `order_emails`   | `PaymentEmailConsumer`      | `email-worker`   |

Messages that still fail after all retries go to the `failed` queue (its own `failed`
fanout exchange).

### Design decisions

- **One queue per consumer.** Every queue gets its own copy of an event, so adding a
  consumer (e.g. stock or analytics) means binding a new queue, with no change to the
  publisher.
- **Dependencies through events.** The confirmation email needs the invoice file, so the
  email-worker listens for `order.invoiced`, not `order.placed`. The email can never be
  sent before the invoice exists, and there is no polling.
- **Routing keys are never forgotten.** Every event implements `OrderEvent::routingKey()`
  and is published through `OrderEventPublisher`. A message without a routing key matches
  no binding, and RabbitMQ silently drops it.
- **Queues are declared up front.** The publish-side `orders` transport declares every
  queue and binding, so events published before a worker has ever started are not lost.
- **Bindings are defined once.** `messenger.yaml` uses YAML anchors, so the publish and
  consume sides cannot drift apart.
- **Retries are safe.** Invoices are written to a fixed path per order, so a retry
  overwrites the file instead of creating a duplicate.

> Both workers run in the same container and share `var/invoices/`. If they run on
> separate machines, invoices must go to shared storage (e.g. S3) instead.

> An order marked paid right away can get its "payment received" email before the
> confirmation, because the confirmation first waits for the invoice step.

## Project structure

```
src/
├── Command/
│   └── GenerateRandomOrderCommand.php   # app:order:random: places test orders
├── Mailer/
│   └── CustomerMailer.php               # Sends plain-text emails, optionally with an attachment
├── Message/
│   ├── OrderEvent.php                   # Interface: every event has a routing key
│   ├── OrderPlaced.php                  # order.placed
│   ├── OrderInvoiced.php                # order.invoiced (carries the invoice path)
│   └── OrderPaid.php                    # order.paid
├── MessageHandler/
│   ├── InvoiceConsumer.php              # order_invoices: writes invoice, publishes OrderInvoiced
│   ├── ConfirmationEmailConsumer.php    # order_emails: confirmation with invoice attached
│   └── PaymentEmailConsumer.php         # order_emails: "payment received" email
├── Model/
│   ├── Customer.php, Order.php, OrderItem.php   # Readonly value objects with validation
│   └── Money.php                        # Formats amounts in cents ("19.99")
└── Service/
    ├── OrderService.php                 # Places orders, marks them paid
    ├── OrderEventPublisher.php          # Publishes events with their routing key
    └── InvoiceWriter.php                # Renders and writes invoice .txt files
config/packages/messenger.yaml           # Exchange, queues, bindings, routing, failure transport
.ddev/config.yaml                        # PHP, RabbitMQ and the two worker daemons
.ddev/messenger-worker.sh                # Worker script: messenger-worker.sh <transport>
```

## Getting started

### Requirements

- [DDEV](https://ddev.com/) (recommended). It provides PHP 8.3 with `ext-amqp`, RabbitMQ,
  Mailpit and both workers.
- Without DDEV: PHP ≥ 8.2 with `ext-amqp`, Composer 2, RabbitMQ and an SMTP server such as
  Mailpit. Run `bin/console messenger:consume order_invoices` and
  `bin/console messenger:consume order_emails` yourself.

### Installation

```bash
git clone https://github.com/jparengkuan/shop-checkout-pipeline.git
cd shop-checkout-pipeline
ddev start
ddev composer install
ddev restart   # starts the workers with the installed dependencies
```

`ddev start` runs two worker daemons from `.ddev/messenger-worker.sh`. Each waits for
RabbitMQ, then consumes its queue, and restarts hourly or at 128 MB to pick up new code.

| Daemon           | Consumes         |
|------------------|------------------|
| `invoice-worker` | `order_invoices` |
| `email-worker`   | `order_emails`   |

> After changing PHP code, restart the workers so they load it:
> `ddev exec supervisorctl restart webextradaemons:invoice-worker webextradaemons:email-worker`

## Usage

Place random test orders. Each goes through RabbitMQ like a real order:

```bash
ddev exec bin/console app:order:random              # one order
ddev exec bin/console app:order:random --count=5    # five orders
ddev exec bin/console app:order:random --paid       # also mark each order as paid
```

Per order, the customer receives:

| Email                             | When                   | Attachment         |
|-----------------------------------|------------------------|--------------------|
| `Your order <id>`                 | after the invoice step | `invoice-<id>.txt` |
| `Payment received for order <id>` | with `--paid`          | none               |

### Example invoice

```
INVOICE
====================================================
Invoice number: INV-e46d80a863fd99836fa3209d3d8f0e78
Invoice date:   2026-09-24

Bill to:
  Daan Mulder
  daan.mulder@example.com
  Customer ID: cust-e9eb15a8

Product                Qty   Unit price        Total
----------------------------------------------------
HOODIE-GRY               1        49.99        49.99
SOCKS-3PK                1         8.99         8.99
SNEAKER-WHT              3        89.00       267.00
----------------------------------------------------
Subtotal (excl. VAT)                          269.40
VAT 21%                                        56.58
Total (incl. VAT)                             325.98
```

Prices include 21% VAT; the invoice shows the breakdown.

### Where to look

| What             | Where                                                              |
|------------------|--------------------------------------------------------------------|
| Sent emails      | `ddev mailpit`                                                     |
| Invoice files    | `var/invoices/`                                                    |
| RabbitMQ UI      | https://shop-checkout-pipeline.ddev.site:15673 (`guest` / `guest`) |
| Worker logs      | `ddev logs -s web`                                                 |
| Failed messages  | RabbitMQ UI → Queues → `failed`                                    |
| Retry failed     | `ddev exec bin/console messenger:failed:retry`                     |
| Handler overview | `ddev exec bin/console debug:messenger`                            |

## Extending the pipeline

**React to an existing event**, e.g. lower stock for every new order:

1. Add a queue in `config/packages/messenger.yaml`: under `orders` → `queues`, add
   `order_stock: &order_stock_queue { binding_keys: ['order.placed'] }`, plus a consume-side
   `order_stock` transport that reuses `*orders_exchange` and `*order_stock_queue`.
2. Add a handler: `#[AsMessageHandler(fromTransport: 'order_stock')]` with `__invoke(OrderPlaced $message)`.
3. Add a worker in `.ddev/config.yaml`: `bash .ddev/messenger-worker.sh order_stock`.
4. Run `ddev restart`.

**Add a new event**, e.g. `order.shipped`:

1. Create `OrderShipped implements OrderEvent` with `routingKey()` returning `'order.shipped'`.
2. Publish it with `OrderEventPublisher::publish()`. It is routed to the `orders`
   exchange automatically.
3. Bind a queue to `order.shipped` and add a handler, as above.

> RabbitMQ keeps existing bindings when you remove them from the config. After removing
> or changing a binding, delete it in the RabbitMQ UI (Exchanges → `orders` → Bindings).

## Configuration

Environment variables (defaults in `.env`; override in `.env.local`):

| Variable                  | Default                                   | Purpose                      |
|---------------------------|-------------------------------------------|------------------------------|
| `MESSENGER_TRANSPORT_DSN` | `amqp://guest:guest@rabbitmq:5672/%2f`    | RabbitMQ connection          |
| `MAILER_DSN`              | `null://null` (DDEV points it at Mailpit) | Mail transport               |
| `ORDER_EMAIL_FROM`        | `shop@example.com`                        | Sender address of all emails |

## Code quality

PHP_CodeSniffer (PSR-12, `phpcs.xml.dist`) and PHPStan (level 5 with the Symfony
extension, `phpstan.dist.neon`):

```bash
ddev composer lint   # phpcs + phpstan
ddev composer fix    # auto-fix coding standard issues (phpcbf)
```

Both run on every push and pull request via GitHub Actions (`.github/workflows/lint.yml`).

## License

Released under the MIT License. See [LICENSE](LICENSE).
