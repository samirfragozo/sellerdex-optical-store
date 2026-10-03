<?php

namespace App\Support;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of products.stock: every change becomes a kardex movement
 * carrying the balance it left. Nothing is written when the company doesn't
 * track inventory or the product isn't stockable (services, made-to-order lenses).
 */
class StockLedger
{
    public static function record(Product $product, StockMovementType $type, int $quantity, ?Model $source = null, ?string $reason = null): ?StockMovement
    {
        if (! $product->is_stockable || ! $product->company?->tracksInventory()) {
            return null;
        }

        return DB::transaction(function () use ($product, $type, $quantity, $source, $reason): StockMovement {
            // Lock the row so concurrent sales of the same product never lose a unit.
            $locked = Product::withoutGlobalScopes()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $balance = (int) $locked->stock + $quantity;

            Product::withoutGlobalScopes()->whereKey($locked->getKey())->update(['stock' => $balance]);
            $product->setRawAttributes([...$product->getAttributes(), 'stock' => $balance], true);

            return StockMovement::create([
                'company_id' => $locked->company_id,
                'product_id' => $locked->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reason' => $reason,
                'user_id' => Auth::id(),
            ]);
        });
    }
}
