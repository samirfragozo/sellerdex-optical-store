<?php

namespace App\Models;

use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Traits\BelongsToCompany;
use Database\Factories\FiscalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** A document a sale or return got: an internal receipt or the number of one issued in another system. Never changed or deleted. */
#[Fillable(['company_id', 'sale_id', 'sale_return_id', 'document_type', 'source', 'number', 'issued_at', 'status', 'cufe_or_cude', 'pdf_path', 'reference_fiscal_document_id', 'registered_by'])]
class FiscalDocument extends Model
{
    /** @use HasFactory<FiscalDocumentFactory> */
    use BelongsToCompany, HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fiscal documents are immutable.'));
        static::deleting(fn () => throw new LogicException('Fiscal documents are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'document_type' => FiscalDocumentType::class,
            'source' => FiscalDocumentSource::class,
            'status' => FiscalDocumentStatus::class,
            'issued_at' => 'date',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /** The document a note corrects. */
    public function reference(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_fiscal_document_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
