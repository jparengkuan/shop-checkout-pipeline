<?php

namespace App\Service;

use App\Model\Money;
use App\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes plain-text invoices for orders. Used by {@see \App\MessageHandler\InvoiceConsumer};
 * the file is later attached to the order confirmation by {@see \App\MessageHandler\ConfirmationEmailConsumer}.
 */
final readonly class InvoiceWriter
{
    /** VAT rate in percent; prices already include it. */
    private const VAT_PERCENT = 21;

    /**
     * @param string $invoiceDir Directory the invoice files are written to
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/invoices')]
        private string $invoiceDir,
    ) {
    }

    /**
     * Writes the invoice for the order to var/invoices/invoice-<order id>.txt and returns its path.
     *
     * Writing the same order again overwrites the file, so a retry never leaves a duplicate.
     *
     * @throws \RuntimeException when the directory or file cannot be written
     */
    public function write(Order $order): string
    {
        if (!is_dir($this->invoiceDir) && !mkdir($this->invoiceDir, 0o775, true) && !is_dir($this->invoiceDir)) {
            throw new \RuntimeException(sprintf('Cannot create invoice directory "%s".', $this->invoiceDir));
        }

        $path = sprintf('%s/invoice-%s.txt', $this->invoiceDir, $order->id);
        if (false === file_put_contents($path, $this->renderInvoice($order))) {
            throw new \RuntimeException(sprintf('Cannot write invoice "%s".', $path));
        }

        return $path;
    }

    /**
     * Builds the invoice text: header, customer, one line per item and the totals.
     */
    private function renderInvoice(Order $order): string
    {
        $total = $order->totalCents();
        $vat = (int) round($total * self::VAT_PERCENT / (100 + self::VAT_PERCENT));

        $lines = [
            'INVOICE',
            str_repeat('=', 52),
            sprintf('Invoice number: INV-%s', $order->id),
            sprintf('Invoice date:   %s', $order->placedAt->format('Y-m-d')),
            '',
            'Bill to:',
            sprintf('  %s', $order->customer->name),
            sprintf('  %s', $order->customer->email),
            sprintf('  Customer ID: %s', $order->customer->id),
            '',
            sprintf('%-20s %5s %12s %12s', 'Product', 'Qty', 'Unit price', 'Total'),
            str_repeat('-', 52),
        ];

        foreach ($order->items as $item) {
            $lines[] = sprintf(
                '%-20s %5d %12s %12s',
                $item->sku,
                $item->quantity,
                Money::format($item->unitPriceCents),
                Money::format($item->totalCents()),
            );
        }

        array_push(
            $lines,
            str_repeat('-', 52),
            sprintf('%-39s %12s', 'Subtotal (excl. VAT)', Money::format($total - $vat)),
            sprintf('%-39s %12s', sprintf('VAT %d%%', self::VAT_PERCENT), Money::format($vat)),
            sprintf('%-39s %12s', 'Total (incl. VAT)', Money::format($total)),
            '',
        );

        return implode("\n", $lines);
    }
}
