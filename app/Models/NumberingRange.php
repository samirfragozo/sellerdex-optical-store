<?php

namespace App\Models;

use App\Enums\FiscalDocumentType;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Internal numbering (the comprobante de venta). Electronic numbering belongs to whoever issues those documents. */
#[Fillable(['company_id', 'document_type', 'prefix', 'next_number', 'is_active'])]
class NumberingRange extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['document_type' => FiscalDocumentType::class, 'next_number' => 'integer', 'is_active' => 'boolean'];
    }

    /** The next number of the company's range for this type, created on first use; numbers are never reused or skipped. */
    public static function takeNext(int $companyId, FiscalDocumentType $type): string
    {
        return DB::transaction(function () use ($companyId, $type): string {
            // Race-safe first use: the unique (company_id, document_type) index keeps a single row.
            DB::table('numbering_ranges')->insertOrIgnore([
                'company_id' => $companyId, 'document_type' => $type->value, 'next_number' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $range = self::withoutGlobalScopes()->where('company_id', $companyId)->where('document_type', $type->value)
                ->lockForUpdate()->firstOrFail();
            $number = $range->next_number;
            $range->increment('next_number');

            return ($range->prefix ?? '').str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        });
    }
}
