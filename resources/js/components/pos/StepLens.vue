<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue';
import type {
    LensProduct,
    LensSpecs,
    ProductProp,
} from '@/composables/useLensCatalog';
import { useLensCatalog } from '@/composables/useLensCatalog';

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

const catalog = useLensCatalog(toRef(props, 'products'));

const processes = computed(() =>
    selection.value.design ? catalog.processesFor(selection.value.design) : [],
);
const materials = computed(() =>
    selection.value.design && selection.value.process
        ? catalog.materialsFor(selection.value.design, selection.value.process)
        : [],
);
const filters = computed(() =>
    selection.value.design &&
    selection.value.process &&
    selection.value.material
        ? catalog.filtersFor(
              selection.value.design,
              selection.value.process,
              selection.value.material,
          )
        : [],
);

function isRecommended(dim: keyof LensSpecs, value: string): boolean {
    return props.recommended?.[dim] === value;
}

// Reset downstream dimensions when an upstream one changes.
function pickDesign(v: string) {
    selection.value = { design: v, process: '', material: '', filter: '' };
}

function pickProcess(v: string) {
    selection.value = {
        ...selection.value,
        process: v,
        material: '',
        filter: '',
    };
}

function pickMaterial(v: string) {
    selection.value = { ...selection.value, material: v, filter: '' };
}

function pickFilter(v: string) {
    selection.value = { ...selection.value, filter: v };
}

watch(
    selection,
    (sel) => {
        // Update the resolvedLens model BEFORE emitting change: the parent's @change handler reads resolvedLens.
        resolvedLens.value =
            sel.design && sel.process && sel.material && sel.filter
                ? catalog.resolve(sel)
                : null;
        emit('change', sel);
    },
    { deep: true },
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

const designs = catalog.designs;
const noCombo = ref(false);

watch(filters, (f) => {
    noCombo.value = selection.value.material !== '' && f.length === 0;
});
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

        <!-- Diseño -->
        <div>
            <span class="mb-1 block text-sm font-medium">Diseño</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="d in designs"
                    :key="d"
                    type="button"
                    :class="chip(selection.design === d)"
                    @click="pickDesign(d)"
                >
                    {{ d }}
                    <span v-if="isRecommended('design', d)" class="ml-1 text-xs"
                        >★</span
                    >
                </button>
            </div>
        </div>

        <!-- Gama / proceso -->
        <div v-if="selection.design">
            <span class="mb-1 block text-sm font-medium">Gama</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="p in processes"
                    :key="p"
                    type="button"
                    :class="chip(selection.process === p)"
                    @click="pickProcess(p)"
                >
                    {{ p }}
                    <span
                        v-if="isRecommended('process', p)"
                        class="ml-1 text-xs"
                        >★</span
                    >
                </button>
            </div>
        </div>

        <!-- Material -->
        <div v-if="selection.process">
            <span class="mb-1 block text-sm font-medium">Material</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="m in materials"
                    :key="m"
                    type="button"
                    :class="chip(selection.material === m)"
                    @click="pickMaterial(m)"
                >
                    {{ m }}
                    <span
                        v-if="isRecommended('material', m)"
                        class="ml-1 text-xs"
                        >★</span
                    >
                </button>
            </div>
        </div>

        <!-- Filtro -->
        <div v-if="selection.material">
            <span class="mb-1 block text-sm font-medium">Filtro</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="f in filters"
                    :key="f"
                    type="button"
                    :class="chip(selection.filter === f)"
                    @click="pickFilter(f)"
                >
                    {{ f }}
                    <span v-if="isRecommended('filter', f)" class="ml-1 text-xs"
                        >★</span
                    >
                </button>
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
        <p v-else-if="noCombo" class="text-sm text-muted-foreground">
            Esa combinación no existe en el catálogo. Ajusta material o filtro.
        </p>
    </div>
</template>
