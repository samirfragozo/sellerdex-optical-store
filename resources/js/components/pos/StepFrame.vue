<script setup lang="ts">
import { computed, watch } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProductProp } from '@/composables/useLensCatalog';
import { resolveVariantOptions } from '@/composables/useLensCatalog';
import type { ArmadoMeasurements } from '@/composables/usePosCart';
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
const ownFrameDescription = defineModel<string>('ownFrameDescription', {
    required: true,
});
const ownFrameCondition = defineModel<string>('ownFrameCondition', {
    required: true,
});
const measurements = defineModel<ArmadoMeasurements>('measurements', {
    required: true,
});

type NumericMeasurement = Exclude<keyof ArmadoMeasurements, 'frame_type'>;

const measurementFields: NumericMeasurement[] = [
    'od_height',
    'os_height',
    'frame_a',
    'frame_b',
    'frame_dbl',
];

const frameTypes = ['full_rim', 'semi_rimless', 'rimless'] as const;

// An emptied number input yields '' — keep it null so the payload never
// sends a blank string for a numeric field.
function setMeasurement(key: NumericMeasurement, value: string | number): void {
    measurements.value[key] = value === '' ? null : Number(value);
}

function onFrameTypeSelect(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    measurements.value.frame_type =
        (value as ArmadoMeasurements['frame_type']) || null;
}

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

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <div class="grid gap-1">
                <Label for="own_frame_description">{{
                    trans('app.fields.customer_frame_description')
                }}</Label>
                <Input
                    id="own_frame_description"
                    v-model="ownFrameDescription"
                    maxlength="255"
                />
            </div>
            <div class="grid gap-1">
                <Label for="own_frame_condition">{{
                    trans('app.fields.customer_frame_condition')
                }}</Label>
                <Input
                    id="own_frame_condition"
                    v-model="ownFrameCondition"
                    maxlength="255"
                />
            </div>
        </div>

        <fieldset class="flex flex-col gap-3 border-t pt-3">
            <legend class="sr-only">
                {{ trans('app.pos.frame_form.measurements') }}
            </legend>
            <div>
                <p class="text-sm font-medium">
                    {{ trans('app.pos.frame_form.measurements') }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ trans('app.pos.frame_form.measurements_hint') }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="col-span-2 grid gap-1 sm:col-span-3">
                    <Label for="frame_type">{{
                        trans('app.fields.frame_type')
                    }}</Label>
                    <select
                        id="frame_type"
                        :value="measurements.frame_type ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        @change="onFrameTypeSelect"
                    >
                        <option value="">
                            {{ trans('app.pos.select_option') }}
                        </option>
                        <option
                            v-for="type in frameTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ trans(`app.frame_type.${type}`) }}
                        </option>
                    </select>
                </div>
                <div
                    v-for="key in measurementFields"
                    :key="key"
                    class="grid gap-1"
                >
                    <Label :for="key">{{ trans(`app.fields.${key}`) }}</Label>
                    <Input
                        :id="key"
                        type="number"
                        step="0.5"
                        inputmode="decimal"
                        :model-value="measurements[key] ?? ''"
                        @update:model-value="setMeasurement(key, $event)"
                    />
                </div>
            </div>
        </fieldset>
    </div>
</template>
