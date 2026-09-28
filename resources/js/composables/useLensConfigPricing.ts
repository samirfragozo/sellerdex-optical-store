// resources/js/composables/useLensConfigPricing.ts
import { computed, ref  } from 'vue';
import type {Ref} from 'vue';
import type { LensCatalogProp, LensCombinationProp } from '@/composables/useLensCatalog';

export function useLensConfigPricing(catalog: Ref<LensCatalogProp>) {
    const typeId = ref<number | null>(null);
    const technologyId = ref<number | null>(null);
    const materialId = ref<number | null>(null);
    const treatmentIds = ref<number[]>([]);

    const combination = computed<LensCombinationProp | null>(() => {
        if (typeId.value === null || technologyId.value === null || materialId.value === null) {
            return null;
        }

        return (
            catalog.value.combinations.find(
                (c) =>
                    c.lens_type_id === typeId.value &&
                    c.lens_technology_id === technologyId.value &&
                    c.lens_material_id === materialId.value,
            ) ?? null
        );
    });

    const selectedTreatments = computed(() =>
        catalog.value.treatments.filter((t) => treatmentIds.value.includes(t.id)),
    );

    const isComplete = computed(() => combination.value !== null);

    const resolvedPrice = computed<number>(() => {
        if (!isComplete.value) {
            return 0;
        }

        return (
            (combination.value as LensCombinationProp).price +
            (combination.value as LensCombinationProp).installation_price +
            selectedTreatments.value.reduce((sum, t) => sum + t.price, 0)
        );
    });

    const resolvedCost = computed<number>(() => {
        if (!isComplete.value) {
            return 0;
        }

        return (
            (combination.value as LensCombinationProp).cost +
            selectedTreatments.value.reduce((sum, t) => sum + t.cost, 0)
        );
    });

    function selectType(id: number): void {
        typeId.value = id;
        technologyId.value = null;
        materialId.value = null;
    }

    function selectTechnology(id: number): void {
        technologyId.value = id;
        materialId.value = null;
    }

    function selectMaterial(id: number): void {
        materialId.value = id;
    }

    function toggleTreatment(id: number): void {
        treatmentIds.value = treatmentIds.value.includes(id)
            ? treatmentIds.value.filter((t) => t !== id)
            : [...treatmentIds.value, id];
    }

    function reset(): void {
        typeId.value = null;
        technologyId.value = null;
        materialId.value = null;
        treatmentIds.value = [];
    }

    return {
        typeId,
        technologyId,
        materialId,
        treatmentIds,
        combination,
        selectedTreatments,
        isComplete,
        resolvedPrice,
        resolvedCost,
        selectType,
        selectTechnology,
        selectMaterial,
        toggleTreatment,
        reset,
    };
}
