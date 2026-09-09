import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { ProductProp } from '@/composables/useLensCatalog';

export function useProductOptions(product: Ref<ProductProp | null>) {
    const selected = ref<Record<number, number>>({});

    // Reset selections whenever the underlying product changes.
    watch(product, () => {
        selected.value = {};
    });

    const groups = computed(() => product.value?.option_groups ?? []);

    const selectedOptions = computed(() =>
        groups.value
            .map((g) => g.options.find((o) => o.id === selected.value[g.id]))
            .filter((o): o is NonNullable<typeof o> => !!o),
    );

    const isComplete = computed(() =>
        groups.value
            .filter((g) => g.is_required)
            .every((g) => selected.value[g.id] !== undefined),
    );

    const resolvedPrice = computed(
        () =>
            (product.value?.price ?? 0) +
            selectedOptions.value.reduce((sum, o) => sum + o.price, 0),
    );

    const resolvedCost = computed(
        () =>
            (product.value?.cost ?? 0) +
            selectedOptions.value.reduce((sum, o) => sum + o.cost, 0),
    );

    const resolvedName = computed(() =>
        [product.value?.name, ...selectedOptions.value.map((o) => o.name)]
            .filter(Boolean)
            .join(' '),
    );

    const optionIds = computed(() => selectedOptions.value.map((o) => o.id));

    function select(groupId: number, optionId: number): void {
        selected.value = { ...selected.value, [groupId]: optionId };
    }

    return {
        selected,
        isComplete,
        resolvedPrice,
        resolvedCost,
        resolvedName,
        optionIds,
        select,
    } as {
        selected: Ref<Record<number, number>>;
        isComplete: ComputedRef<boolean>;
        resolvedPrice: ComputedRef<number>;
        resolvedCost: ComputedRef<number>;
        resolvedName: ComputedRef<string>;
        optionIds: ComputedRef<number[]>;
        select: (groupId: number, optionId: number) => void;
    };
}
