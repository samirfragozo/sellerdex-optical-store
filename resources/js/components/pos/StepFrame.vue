<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { ProductProp } from '@/composables/useLensCatalog';
import { useProductOptions } from '@/composables/useProductOptions';
import { useTranslations } from '@/composables/useTranslations';

interface FrameSelection {
    product_id: number;
    description: string;
    unit_price: number;
    unit_cost?: number;
}

const props = defineProps<{
    frameProducts: ProductProp[];
}>();

const { trans } = useTranslations();

const frame = defineModel<FrameSelection | null>('frame', { required: true });
const ownFrame = defineModel<boolean>('ownFrame', { required: true });

const standaloneFrames = computed(() =>
    props.frameProducts.filter((p) => p.option_groups.length === 0),
);
const variantBases = computed(() =>
    props.frameProducts.filter((p) => p.option_groups.length > 0),
);

const pickedBase = ref<ProductProp | null>(null);
const productOptions = useProductOptions(pickedBase);
const noVariant = ref(false);

function pickBase(p: ProductProp): void {
    pickedBase.value = p;
    frame.value = null;
    noVariant.value = false;
}

function onFrameSelect(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    pickedBase.value = null;
    noVariant.value = false;

    if (!value) {
        frame.value = null;

        return;
    }

    const id = parseInt(value, 10);
    const product = standaloneFrames.value.find((p) => p.id === id);

    if (product) {
        frame.value = {
            product_id: product.id,
            description: product.name,
            unit_price: product.price,
            unit_cost: product.cost,
        };
    }
}

// When editing an existing armado, preselect the frame base and its options
// that were already chosen instead of starting the wizard step blank.
// Standalone frames (sunglasses) need no extra work: the <select> already
// binds to `frame.product_id` directly.
watch(
    () => variantBases.value,
    async (bases) => {
        if (pickedBase.value || !frame.value || ownFrame.value) {
            return;
        }

        for (const base of bases) {
            const variant = base.variants.find(
                (v) => v.id === frame.value?.product_id,
            );

            if (!variant) {
                continue;
            }

            pickedBase.value = base;
            await nextTick(); // let useProductOptions' own product watcher reset `selected` first.

            const ids = new Set(variant.option_ids);
            const selected: Record<number, number> = {};

            for (const group of base.option_groups) {
                const opt = group.options.find((o) => ids.has(o.id));

                if (opt) {
                    selected[group.id] = opt.id;
                }
            }

            productOptions.selected.value = selected;
            break;
        }
    },
    { immediate: true },
);

watch(
    () => productOptions.optionIds.value,
    () => {
        if (!pickedBase.value) {
            noVariant.value = false;

            return;
        }

        if (!productOptions.isComplete.value) {
            frame.value = null;
            noVariant.value = false;

            return;
        }

        const selected = [...productOptions.optionIds.value].sort((a, b) => a - b);
        const variant = pickedBase.value.variants.find((v) => {
            const ids = [...v.option_ids].sort((a, b) => a - b);

            return (
                ids.length === selected.length &&
                ids.every((id, i) => id === selected[i])
            );
        });

        if (!variant) {
            frame.value = null;
            noVariant.value = true;

            return;
        }

        noVariant.value = false;
        frame.value = {
            product_id: variant.id,
            description: productOptions.resolvedName.value,
            unit_price: variant.price,
            unit_cost: variant.cost,
        };
    },
);

const chip = (active: boolean) =>
    [
        'relative rounded-md px-3 py-1.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/50',
        active
            ? 'bg-primary text-primary-foreground'
            : 'border border-input bg-transparent hover:bg-accent',
    ].join(' ');
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Own frame toggle -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="ownFrame" />
            {{ trans('app.pos.frame_form.own_frame_toggle') }}
        </label>

        <div v-if="!ownFrame" class="flex flex-col gap-4">
            <!-- Frame bases with variants (structure, material, ...) -->
            <div v-if="variantBases.length > 0">
                <span class="mb-1 block text-sm font-medium">{{
                    trans('app.pos.frame_form.pick_base')
                }}</span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="p in variantBases"
                        :key="p.id"
                        type="button"
                        :class="chip(pickedBase?.id === p.id)"
                        @click="pickBase(p)"
                    >
                        {{ p.name }}
                    </button>
                </div>

                <div
                    v-for="group in pickedBase?.option_groups ?? []"
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
                            :class="
                                chip(
                                    productOptions.selected.value[group.id] ===
                                        opt.id,
                                )
                            "
                            @click="productOptions.select(group.id, opt.id)"
                        >
                            {{ opt.name }}
                        </button>
                    </div>
                </div>

                <p v-if="noVariant" class="mt-2 text-sm text-muted-foreground">
                    {{ trans('app.pos.frame_form.no_variant') }}
                </p>
            </div>

            <!-- Standalone frame products (no variants), e.g. sunglasses -->
            <div>
                <Label for="frame_product">{{
                    trans('app.pos.frame_form.select_frame')
                }}</Label>
                <select
                    id="frame_product"
                    :value="frame && !pickedBase ? frame.product_id : ''"
                    class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                    @change="onFrameSelect"
                >
                    <option value="">{{ trans('app.pos.none_option') }}</option>
                    <option
                        v-for="product in standaloneFrames"
                        :key="product.id"
                        :value="product.id"
                    >
                        {{ product.name }}
                    </option>
                </select>
            </div>
        </div>
    </div>
</template>
