<?php

namespace App\Actions;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterSale
{
    /**
     * Create a sale with its line items and an optional initial payment.
     *
     * @param  array{customer_id:int, document_type?:string, prescription_id?:int|null, notes?:string|null, sold_at?:string|null, discount?:int, items:array<int,array{product_id?:int|null, description:string, quantity:int, unit_price:int, unit_cost?:int}>, payment?:array{payment_method_id:int, amount:int, reference?:string|null}|null}  $data
     */
    public function handle(array $data, User $seller): Sale
    {
        return DB::transaction(function () use ($data, $seller): Sale {
            $sale = Sale::create([
                'customer_id' => $data['customer_id'],
                'seller_id' => $seller->id,
                'created_by' => $seller->id,
                'prescription_id' => $data['prescription_id'] ?? null,
                'document_type' => $data['document_type'] ?? 'order',
                'discount' => $data['discount'] ?? 0,
                'sold_at' => $data['sold_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                ]);
            }

            $sale->recalculateTotals();

            if (! empty($data['payment']) && ($data['payment']['amount'] ?? 0) > 0) {
                $sale->payments()->create([
                    'payment_method_id' => $data['payment']['payment_method_id'],
                    'amount' => $data['payment']['amount'],
                    'paid_at' => now()->toDateString(),
                    'received_by' => $seller->id,
                    'reference' => $data['payment']['reference'] ?? null,
                ]);
            }

            return $sale->refresh();
        });
    }
}
