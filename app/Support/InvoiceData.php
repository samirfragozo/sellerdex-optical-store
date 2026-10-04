<?php

namespace App\Support;

use App\Models\Customer;
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

    /** @return array{buyer: array{document_type: ?string, id_number: string, name: string, phone: ?string, email: ?string, address: ?string}, lines: list<array{description: string, quantity: int, unit_price: int, base: int, tax: int, tax_rate: float, total: int}>, totals: array{base: int, tax: int, total: int}} */
    public static function for(Sale $sale): array
    {
        $sale->loadMissing('items');
        $factor = $sale->chargedFactor();

        $lines = $sale->items->map(fn (SaleItem $item): array => self::line($item->description, $item->quantity, (int) round($item->line_total * $factor), (float) $item->tax_rate))->values()->all();

        // ponytail: the per-line rounding drift lands on the biggest line, so the items always add up to the discounted base.
        $drift = $sale->subtotal - $sale->discount - (int) collect($lines)->sum('total');
        if ($lines !== [] && $drift !== 0) {
            $biggest = array_key_first(collect($lines)->sortByDesc('total')->all());
            $lines[$biggest] = self::line($lines[$biggest]['description'], $lines[$biggest]['quantity'], $lines[$biggest]['total'] + $drift, $lines[$biggest]['tax_rate']);
        }

        if ((float) $sale->surcharge_percent > 0) {
            $lines[] = self::line(__('app.invoice_data.surcharge'), 1, $sale->total - ($sale->subtotal - $sale->discount), 0.0);
        }

        // Soft-deleted customers are still the buyer of their old sales.
        $customer = $sale->customer_id
            ? Customer::withoutGlobalScopes()->where('company_id', $sale->company_id)->find($sale->customer_id)
            : null;

        return [
            'buyer' => [
                'document_type' => $customer?->id_number ? $customer->document_type?->label() : null,
                'id_number' => $customer?->id_number ?: self::FINAL_CONSUMER_ID,
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

    /** @return array{description: string, quantity: int, unit_price: int, base: int, tax: int, tax_rate: float, total: int} */
    private static function line(string $description, int $quantity, int $total, float $rate): array
    {
        $tax = $rate > 0 ? (int) round($total - $total / (1 + $rate / 100)) : 0;

        return [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? (int) round($total / $quantity) : 0,
            'base' => $total - $tax,
            'tax' => $tax,
            'tax_rate' => $rate,
            'total' => $total,
        ];
    }
}
