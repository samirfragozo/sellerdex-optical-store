<?php

namespace Database\Factories;

use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Models\FiscalDocument;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalDocument>
 */
class FiscalDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'company_id' => fn (array $attributes): ?int => Sale::withoutGlobalScopes()->find($attributes['sale_id'])?->company_id,
            'document_type' => FiscalDocumentType::PosElectronic,
            'source' => FiscalDocumentSource::ExternalManual,
            'number' => fake()->unique()->numerify('FE-#####'),
            'issued_at' => today()->toDateString(),
            'status' => FiscalDocumentStatus::Registered,
        ];
    }
}
