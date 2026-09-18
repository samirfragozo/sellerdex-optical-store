<script setup lang="ts">
import { computed, watch } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { ProductProp } from '@/composables/useLensCatalog';
import { resolveVariantOptions } from '@/composables/useLensCatalog';
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

// Every real, sellable combination across the catalog — never the raw
// type/material building blocks. Stock isn't filtered here, matching every
// other category in the general catalog (out of stock still sells).
const frameOptions = computed(() =>
    props.frameProducts.flatMap(resolveVariantOptions),
);

function onFrameSelect(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    if (!value) {
        frame.value = null;

        return;
    }

    const id = parseInt(value, 10);
    const option = frameOptions.value.find((o) => o.value === id);

    frame.value = option
        ? {
              product_id: option.value,
              description: option.label,
              unit_price: option.unit_price,
              unit_cost: option.unit_cost,
          }
        : null;
}

// A single available combination offers no real choice, so skip the click.
watch(
    frameOptions,
    (options) => {
        if (frame.value || ownFrame.value || options.length !== 1) {
            return;
        }

        const [only] = options;
        frame.value = {
            product_id: only.value,
            description: only.label,
            unit_price: only.unit_price,
            unit_cost: only.unit_cost,
        };
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Own frame toggle -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="ownFrame" />
            {{ trans('app.pos.frame_form.own_frame_toggle') }}
        </label>

        <div v-if="!ownFrame">
            <Label for="frame_product">{{
                trans('app.pos.frame_form.select_frame')
            }}</Label>
            <select
                id="frame_product"
                :value="frame?.product_id ?? ''"
                class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                @change="onFrameSelect"
            >
                <option value="">{{ trans('app.pos.select_option') }}</option>
                <option
                    v-for="option in frameOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>
    </div>
</template>
