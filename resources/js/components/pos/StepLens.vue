<script setup lang="ts">
import { computed, watch } from 'vue';
import type { LensCatalogProp } from '@/composables/useLensCatalog';
import { useLensConfigPricing } from '@/composables/useLensConfigPricing';
import type { ArmadoLensLine } from '@/composables/usePosCart';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    catalog: LensCatalogProp;
    /** Existing configuration when re-editing an armado. */
    initial?: ArmadoLensLine | null;
}>();

const emit = defineEmits<{ change: [ArmadoLensLine | null] }>();

const {
    typeId,
    technologyId,
    materialId,
    packageId,
    treatmentIds,
    combination,
    selectedTreatments,
    isComplete,
    resolvedPrice,
    resolvedCost,
    selectType,
    selectTechnology,
    selectMaterial,
    selectPackage,
    toggleTreatment,
} = useLensConfigPricing(computed(() => props.catalog));

// --- Step chaining: each picker only offers values a combination exists for.
const availableTechnologies = computed(() =>
    props.catalog.technologies.filter((t) =>
        props.catalog.combinations.some(
            (c) =>
                c.lens_type_id === typeId.value &&
                c.lens_technology_id === t.id,
        ),
    ),
);

const availableMaterials = computed(() =>
    props.catalog.materials.filter((m) =>
        props.catalog.combinations.some(
            (c) =>
                c.lens_type_id === typeId.value &&
                c.lens_technology_id === technologyId.value &&
                c.lens_material_id === m.id,
        ),
    ),
);

const nameOf = (
    list: { id: number; name: string }[],
    id: number | null,
): string => list.find((i) => i.id === id)?.name ?? '';

const description = computed(() =>
    [
        nameOf(props.catalog.types, typeId.value),
        nameOf(props.catalog.technologies, technologyId.value),
        nameOf(props.catalog.materials, materialId.value),
        ...selectedTreatments.value.map((t) => t.name),
    ]
        .filter(Boolean)
        .join(' — '),
);

const config = computed<ArmadoLensLine | null>(() =>
    isComplete.value && packageId.value !== null
        ? {
              description: description.value,
              quantity: 1,
              lens_type_id: typeId.value as number,
              lens_technology_id: technologyId.value as number,
              lens_material_id: materialId.value as number,
              lens_package_id: packageId.value,
              treatment_ids: treatmentIds.value,
              price: resolvedPrice.value,
              cost: resolvedCost.value,
          }
        : null,
);

// Seed the pickers when re-editing an armado, so the wizard shows the
// configuration the cart row already holds instead of starting blank.
if (props.initial) {
    selectType(props.initial.lens_type_id);
    selectTechnology(props.initial.lens_technology_id);
    selectMaterial(props.initial.lens_material_id);
    selectPackage(props.initial.lens_package_id);
    props.initial.treatment_ids.forEach(toggleTreatment);
}

// Immediate: re-opening an armado whose combination was since deleted or
// deactivated must emit the resulting null instead of leaving the stale value.
watch(config, (value) => emit('change', value), { immediate: true });

const formatCOP = (value: number): string =>
    '$' + new Intl.NumberFormat('es-CO').format(value);

// Chip button class helper.
const chip = (active: boolean) =>
    [
        'relative rounded-md px-3 py-1.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/50',
        active
            ? 'bg-primary text-primary-foreground'
            : 'border border-input bg-transparent hover:bg-accent',
    ].join(' ');
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- 1. Tipo -->
        <div>
            <span class="mb-1 block text-sm font-medium">{{
                trans('app.pos.lens_form.pick_type')
            }}</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="t in catalog.types"
                    :key="t.id"
                    type="button"
                    :aria-pressed="typeId === t.id"
                    :class="chip(typeId === t.id)"
                    @click="selectType(t.id)"
                >
                    {{ t.name }}
                </button>
            </div>
        </div>

        <!-- 2. Tecnología -->
        <div v-if="typeId !== null">
            <span class="mb-1 block text-sm font-medium">{{
                trans('app.pos.lens_form.pick_technology')
            }}</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="t in availableTechnologies"
                    :key="t.id"
                    type="button"
                    :aria-pressed="technologyId === t.id"
                    :class="chip(technologyId === t.id)"
                    @click="selectTechnology(t.id)"
                >
                    {{ t.name }}
                </button>
            </div>
        </div>

        <!-- 3. Material -->
        <div v-if="technologyId !== null">
            <span class="mb-1 block text-sm font-medium">{{
                trans('app.pos.lens_form.pick_material')
            }}</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="m in availableMaterials"
                    :key="m.id"
                    type="button"
                    :aria-pressed="materialId === m.id"
                    :class="chip(materialId === m.id)"
                    @click="selectMaterial(m.id)"
                >
                    {{ m.name }}
                </button>
            </div>
            <p
                v-if="materialId !== null && combination === null"
                class="mt-1 text-sm text-amber-700 dark:text-amber-400"
            >
                {{ trans('app.pos.lens_form.invalid_combination') }}
            </p>
        </div>

        <!-- 4. Tratamientos -->
        <div v-if="combination !== null">
            <span class="mb-1 block text-sm font-medium">{{
                trans('app.pos.lens_form.pick_treatments')
            }}</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="t in catalog.treatments"
                    :key="t.id"
                    type="button"
                    :aria-pressed="treatmentIds.includes(t.id)"
                    :class="chip(treatmentIds.includes(t.id))"
                    @click="toggleTreatment(t.id)"
                >
                    {{ t.name }}
                </button>
            </div>
        </div>

        <!-- 5. Paquete -->
        <div v-if="combination !== null">
            <span class="mb-1 block text-sm font-medium">{{
                trans('app.pos.lens_form.pick_package')
            }}</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="p in catalog.packages"
                    :key="p.id"
                    type="button"
                    :aria-pressed="packageId === p.id"
                    :class="chip(packageId === p.id)"
                    @click="selectPackage(p.id)"
                >
                    {{ p.name }}
                </button>
            </div>
        </div>

        <!-- Resolved configuration -->
        <div
            v-if="config"
            class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2"
        >
            <span class="text-sm font-medium">{{ config.description }}</span>
            <span class="text-sm font-semibold tabular-nums">{{
                formatCOP(config.price)
            }}</span>
        </div>
    </div>
</template>
