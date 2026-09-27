<?php

namespace App\Support;

/**
 * Suggested Tipo x Tecnología x Material lens combinations shown to a brand-new
 * company during onboarding, with sane starting cost/price defaults (COP,
 * integer pesos). Purely in-memory reference data — never persisted as-is;
 * the onboarding wizard turns the combinations the user keeps into real
 * LensType/LensTechnology/LensMaterial/LensCombination rows for their company.
 */
class ReferenceLensCatalog
{
    /**
     * @return array<string, array{type: string, technology: string, material: string, cost: int, price: int, installation_price: int}>
     */
    public static function combinations(): array
    {
        return [
            'monofocal-standard-cr39' => ['type' => 'Monofocal', 'technology' => 'Estándar', 'material' => 'CR-39', 'cost' => 30_000, 'price' => 90_000, 'installation_price' => 15_000],
            'monofocal-standard-policarbonato' => ['type' => 'Monofocal', 'technology' => 'Estándar', 'material' => 'Policarbonato', 'cost' => 45_000, 'price' => 130_000, 'installation_price' => 15_000],
            'monofocal-digital-cr39' => ['type' => 'Monofocal', 'technology' => 'Digital', 'material' => 'CR-39', 'cost' => 60_000, 'price' => 180_000, 'installation_price' => 15_000],
            'monofocal-digital-alto-indice' => ['type' => 'Monofocal', 'technology' => 'Digital', 'material' => 'Alto índice', 'cost' => 90_000, 'price' => 260_000, 'installation_price' => 15_000],
            'bifocal-standard-cr39' => ['type' => 'Bifocal', 'technology' => 'Estándar', 'material' => 'CR-39', 'cost' => 55_000, 'price' => 160_000, 'installation_price' => 20_000],
            'bifocal-standard-policarbonato' => ['type' => 'Bifocal', 'technology' => 'Estándar', 'material' => 'Policarbonato', 'cost' => 75_000, 'price' => 210_000, 'installation_price' => 20_000],
            'progresivo-standard-cr39' => ['type' => 'Progresivo', 'technology' => 'Estándar', 'material' => 'CR-39', 'cost' => 120_000, 'price' => 350_000, 'installation_price' => 25_000],
            'progresivo-digital-cr39' => ['type' => 'Progresivo', 'technology' => 'Digital', 'material' => 'CR-39', 'cost' => 180_000, 'price' => 520_000, 'installation_price' => 25_000],
            'progresivo-digital-policarbonato' => ['type' => 'Progresivo', 'technology' => 'Digital', 'material' => 'Policarbonato', 'cost' => 210_000, 'price' => 600_000, 'installation_price' => 25_000],
            'progresivo-digital-alto-indice' => ['type' => 'Progresivo', 'technology' => 'Digital', 'material' => 'Alto índice', 'cost' => 260_000, 'price' => 750_000, 'installation_price' => 25_000],
        ];
    }
}
