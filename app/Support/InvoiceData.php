<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\SaleItem;

/**
 * What the cashier copies into the other invoicing system: buyer, lines at their charged value (after the
 * sale discount, tax-inclusive, split into base + tax), and any payment surcharge as an untaxed line.
 */
final class InvoiceData
{
    /** DIAN's generic buyer for a sale to an unidentified customer. */
    public const FINAL_CONSUMER_ID = '222222222222';

    /** @return array{buyer: array{document: string, name: string, phone: ?string, email: ?string, address: ?string}, lines: list<array{description: string, quantity: int, unit_price: int, base: int, tax: int, tax_rate: float, total: int}>, totals: array{base: int, tax: int, total: int}} */
    public static function for(Sale $sale): array
    {
        $sale->loadMissing(['customer', 'items']);
        $factor = $sale->chargedFactor();

        $lines = $sale->items->map(function (SaleItem $item) use ($factor): array {
            $total = (int) round($item->line_total * $factor);
            $rate = (float) $item->tax_rate;
            $tax = $rate > 0 ? (int) round($total - $total / (1 + $rate / 100)) : 0;

            return [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->quantity > 0 ? (int) round($total / $item->quantity) : 0,
                'base' => $total - $tax,
                'tax' => $tax,
                'tax_rate' => $rate,
                'total' => $total,
            ];
        })->values()->all();

        // ponytail: the surcharge line also absorbs the ±1 rounding of the per-line totals (a -1 line at worst), so totals always match the sale.
        $surcharge = $sale->total - (int) collect($lines)->sum('total');
        if ($surcharge !== 0) {
            $lines[] = ['description' => __('app.invoice_data.surcharge'), 'quantity' => 1, 'unit_price' => $surcharge, 'base' => $surcharge, 'tax' => 0, 'tax_rate' => 0.0, 'total' => $surcharge];
        }

        $customer = $sale->customer;

        return [
            'buyer' => [
                'document' => $customer?->id_number
                    ? trim(($customer->document_type?->label() ?? '').' '.$customer->id_number)
                    : __('app.invoice_data.final_consumer', ['id' => self::FINAL_CONSUMER_ID]),
                'name' => $customer?->full_name ?? __('app.invoice_data.final_consumer_name'),
                'phone' => $customer?->phone,
                'email' => $customer?->email,
                'address' => $customer?->address,
            ],
            'lines' => $lines,
            'totals' => [
                'base' => (int) collect($lines)->sum('base'),
                'tax' => (int) collect($lines)->sum('tax'),
                'total' => (int) collect($lines)->sum('total'),
            ],
        ];
    }
}
