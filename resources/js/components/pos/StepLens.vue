<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type {
    LensProduct,
    LensSpecs,
    ProductProp,
} from '@/composables/useLensCatalog';
import { useLensOptionPricing } from '@/composables/useLensOptionPricing';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    products: ProductProp[];
    recommended: LensSpecs | null;
    warnings: string[];
}>();

const emit = defineEmits<{ change: [LensSpecs] }>();

const selection = defineModel<LensSpecs>('selection', {
    default: () => ({ design: '', process: '', material: '', filter: '' }),
});
const resolvedLens = defineModel<LensProduct | null>('resolvedLens', {
    default: null,
});

const lenses = computed<ProductProp[]>(() =>
    props.products.filter(
        (p) => p.category_key === 'lens' && p.option_groups.length > 0,
    ),
);
const pickedLens = ref<ProductProp | null>(null);
const pricing = useLensOptionPricing(pickedLens);

function pickLens(p: ProductProp): void {
    pickedLens.value = p;
    selection.value = {
        design: (p.specs?.design as string) ?? '',
        process: '',
        material: '',
        filter: '',
    };
}

// When editing an existing armado, preselect the lens and its options that
// were already chosen instead of starting the wizard step blank.
watch(
    () => lenses.value,
    async (candidates) => {
        if (pickedLens.value || !resolvedLens.value) {
            return;
        }

        const match = candidates.find((p) => p.id === resolvedLens.value?.id);

        if (!match) {
            return;
        }

        pickedLens.value = match;
        await nextTick(); // let useProductOptions' own product watcher reset `selected` first.

        const ids = new Set(resolvedLens.value.option_ids ?? []);
        const selected: Record<number, number> = {};

        for (const group of match.option_groups) {
            const opt = group.options.find((o) => ids.has(o.id));

            if (opt) {
                selected[group.id] = opt.id;
            }
        }

        pricing.selected.value = selected;
    },
    { immediate: true },
);

// ponytail: only the design chip reflects `recommended` (prescription-driven
// hint); per-option (process/material/filter) highlighting was dropped when
// the flat lens catalog was replaced by option groups. Reinstate if a cashier
// needs the star hint deeper in the flow.
function isRecommendedDesign(p: ProductProp): boolean {
    return props.recommended?.design === p.specs?.design;
}

watch(
    () => pricing.optionIds.value,
    () => {
        if (!pricing.isComplete.value || !pickedLens.value) {
            resolvedLens.value = null;

            return;
        }

        resolvedLens.value = {
            id: pickedLens.value.id,
            name: pricing.resolvedName.value,
            price: pricing.resolvedPrice.value,
            cost: pricing.resolvedCost.value,
            specs: pickedLens.value.specs as never,
            option_ids: pricing.optionIds.value,
        };
        emit('change', selection.value);
    },
);

const formatCOP = (value: number): string =>
    '$' + new Intl.NumberFormat('es-CO').format(value);

// Chip button class helper.
const chip = (active: boolean) =>
    [
        'relative rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
        active
            ? 'bg-primary text-primary-foreground'
            : 'border border-input bg-transparent hover:bg-accent',
    ].join(' ');
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Warnings -->
        <div
            v-for="(w, i) in warnings"
            :key="i"
            class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
        >
            {{ w }}
        </div>

        <!-- Tipo de lente -->
        <div>
            <span class="mb-1 block text-sm font-medium">{{
                trans('app.pos.lens_form.pick_lens')
            }}</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="p in lenses"
                    :key="p.id"
                    type="button"
                    :class="chip(pickedLens?.id === p.id)"
                    @click="pickLens(p)"
                >
                    {{ p.name }}
                    <span v-if="isRecommendedDesign(p)" class="ml-1 text-xs"
                        >★</span
                    >
                </button>
            </div>

            <div
                v-for="group in pickedLens?.option_groups ?? []"
                :key="group.id"
                class="mt-3"
            >
                <span class="mb-1 block text-sm font-medium">{{
                    group.name
                }}</span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="opt in group.options"
                        :key="opt.id"
                        type="button"
                        :class="chip(pricing.selected.value[group.id] === opt.id)"
                        @click="pricing.select(group.id, opt.id)"
                    >
                        {{ opt.name }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Resolved product -->
        <div
            v-if="resolvedLens"
            class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2"
        >
            <span class="text-sm font-medium">{{ resolvedLens.name }}</span>
            <span class="text-sm font-semibold tabular-nums">{{
                formatCOP(resolvedLens.price)
            }}</span>
        </div>
    </div>
</template>
