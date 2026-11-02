<?php

namespace App\Support;

/**
 * Suggested counter products (everything sold besides lenses) shown to a brand-new
 * company during onboarding, grouped by category key, with starting price/cost
 * (COP, integer pesos, tax-inclusive). Purely in-memory reference data; the
 * onboarding step turns what the user keeps into real categories and products.
 */
class ReferenceCounterCatalog
{
    /**
     * @return list<array{key: string, name: string, products: list<array{name: string, price: int, cost: int}>}>
     */
    public static function categories(): array
    {
        return [
            ['key' => 'case', 'name' => 'Estuches', 'products' => [
                ['name' => 'Estuche pequeño', 'price' => 12_000, 'cost' => 3_000],
                ['name' => 'Estuche grande', 'price' => 15_000, 'cost' => 4_000],
            ]],
            ['key' => 'cloth', 'name' => 'Paños', 'products' => [
                ['name' => 'Paño microfibra', 'price' => 3_000, 'cost' => 500],
            ]],
            ['key' => 'cleaning', 'name' => 'Líquidos', 'products' => [
                ['name' => 'Líquido limpiador', 'price' => 10_000, 'cost' => 2_000],
            ]],
            ['key' => 'pouch', 'name' => 'Fundas', 'products' => [
                ['name' => 'Funda', 'price' => 3_000, 'cost' => 500],
            ]],
            ['key' => 'bag', 'name' => 'Bolsas', 'products' => [
                ['name' => 'Bolsa plástica', 'price' => 200, 'cost' => 100],
                ['name' => 'Bolsa de papel', 'price' => 1_000, 'cost' => 500],
            ]],
            ['key' => 'service', 'name' => 'Servicios', 'products' => [
                ['name' => 'Examen visual', 'price' => 35_000, 'cost' => 20_000],
            ]],
            ['key' => 'accessory', 'name' => 'Accesorios', 'products' => [
                ['name' => 'Lagrimales de silicona', 'price' => 7_000, 'cost' => 500],
                ['name' => 'Lagrimales de aire', 'price' => 10_000, 'cost' => 1_000],
            ]],
        ];
    }
}
