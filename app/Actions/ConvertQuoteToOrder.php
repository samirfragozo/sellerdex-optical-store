<?php

namespace App\Actions;

use App\Enums\SaleDocumentType;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/** Turn a quote into an order, re-pricing it first when its validity has passed. Returns whether it was re-priced. */
class ConvertQuoteToOrder
{
    public function handle(Sale $quote): bool
    {
        return DB::transaction(function () use ($quote): bool {
            $expired = $quote->isExpiredQuote();

            if ($expired) {
                app(RepriceQuote::class)->handle($quote);
            }

            $quote->refresh()->update(['document_type' => SaleDocumentType::Order]);
            $quote->recalculateStatus();

            return $expired;
        });
    }
}
