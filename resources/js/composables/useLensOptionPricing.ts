import { computed } from 'vue';
import type { Ref } from 'vue';
import type { ProductProp } from '@/composables/useLensCatalog';
import { useProductOptions } from '@/composables/useProductOptions';

// Keep in sync with App\Support\LensPricing (app/Support/LensPricing.php).
const MARKUP_MULTIPLIER = 4;
const TIER_FLOORS: Record<string, number> = {
    'Sin Filtro': 125000,
    'Blue Cut': 195000,
    'Foto Blue Cut': 295000,
};

function lensPrice(cost: number, filter: string | undefined): number {
    const markup = Math.round((cost * MARKUP_MULTIPLIER) / 1000) * 1000;

    return Math.max(markup, filter ? (TIER_FLOORS[filter] ?? 0) : 0);
}

/**
 * Lens pricing: unlike other option-driven products (frames), a lens's final
 * price isn't a flat sum of option prices — it's cost×multiplier, floored by
 * the chosen filter tier. Wraps useProductOptions for selection/cost tracking
 * and replaces its flat resolvedPrice with that formula.
 */
export function useLensOptionPricing(product: Ref<ProductProp | null>) {
    const base = useProductOptions(product);

    const filterName = computed<string | undefined>(() => {
        const group = product.value?.option_groups.find((g) =>
            g.name.startsWith('Filtro'),
        );

        if (!group) {
            return undefined;
        }

        return group.options.find((o) => o.id === base.selected.value[group.id])
            ?.name;
    });

    const resolvedPrice = computed(() =>
        lensPrice(base.resolvedCost.value, filterName.value),
    );

    return { ...base, resolvedPrice } as typeof base;
}
