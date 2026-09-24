<?php

namespace App\Command;

use App\Model\Customer;
use App\Model\OrderItem;
use App\Service\OrderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Places random orders through {@see OrderService}, for testing the order pipeline.
 *
 * Each order goes through RabbitMQ like a real one, so the email worker sends a
 * confirmation to Mailpit. Usage: bin/console app:order:random [--count=N]
 */
#[AsCommand(name: 'app:order:random', description: 'Places one or more random orders')]
class GenerateRandomOrderCommand extends Command
{
    private const FIRST_NAMES = ['Anna', 'Daan', 'Emma', 'Lucas', 'Sophie', 'Noah', 'Julia', 'Sem', 'Tess', 'Finn'];
    private const LAST_NAMES = ['de Vries', 'Jansen', 'Bakker', 'Visser', 'Smit', 'Meijer', 'de Boer', 'Mulder'];

    /** SKU => unit price in cents */
    private const PRODUCTS = [
        'TSHIRT-BLK' => 1999,
        'HOODIE-GRY' => 4999,
        'CAP-RED' => 1450,
        'SOCKS-3PK' => 899,
        'JEANS-SLIM' => 7995,
        'SNEAKER-WHT' => 8900,
    ];

    /**
     * @param OrderService $orderService Service that places each generated order
     */
    public function __construct(
        private readonly OrderService $orderService,
    ) {
        parent::__construct();
    }

    /**
     * Places --count random orders and prints one line per order.
     *
     * @param SymfonyStyle $io    Console output
     * @param int          $count Number of orders to place, at least 1
     *
     * @return int Command::SUCCESS, or Command::INVALID when --count is below 1
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Number of orders to place', shortcut: 'c')]
        int $count = 1,
    ): int {
        if ($count < 1) {
            $io->error('--count must be at least 1.');

            return Command::INVALID;
        }

        for ($i = 0; $i < $count; ++$i) {
            $order = $this->orderService->order($this->randomCustomer(), ...$this->randomItems());

            $io->writeln(sprintf(
                'Placed order <info>%s</info> for %s <%s> (%d item(s))',
                $order->id,
                $order->customer->name,
                $order->customer->email,
                count($order->items),
            ));
        }

        $io->success(sprintf('%d order(s) placed.', $count));

        return Command::SUCCESS;
    }

    /**
     * Builds a customer with a random Dutch name and a matching @example.com address.
     */
    private function randomCustomer(): Customer
    {
        $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
        $slug = strtolower(str_replace(' ', '', $first . '.' . $last));

        return new Customer(
            id: 'cust-' . bin2hex(random_bytes(4)),
            name: $first . ' ' . $last,
            email: $slug . '@example.com',
        );
    }

    /**
     * Picks 1 to 3 different products from {@see self::PRODUCTS}, each with a quantity of 1 to 4.
     *
     * @return list<OrderItem>
     */
    private function randomItems(): array
    {
        $skus = (array) array_rand(self::PRODUCTS, random_int(1, 3));

        return array_map(
            static fn (string $sku) => new OrderItem($sku, random_int(1, 4), self::PRODUCTS[$sku]),
            $skus,
        );
    }
}
