<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { ProductProp } from '@/composables/useLensCatalog';

interface FrameSelection {
    product_id: number;
    description: string;
    unit_price: number;
}

const props = defineProps<{
    frameProducts: ProductProp[];
}>();

const frame = defineModel<FrameSelection | null>('frame', { required: true });
const ownFrame = defineModel<boolean>('ownFrame', { required: true });

function onFrameSelect(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    if (!value) {
        frame.value = null;

        return;
    }

    const id = parseInt(value, 10);
    const product = props.frameProducts.find((p) => p.id === id);

    if (product) {
        frame.value = {
            product_id: product.id,
            description: product.name,
            unit_price: product.price,
        };
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Own frame toggle -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="ownFrame" />
            El cliente trae su montura
        </label>

        <!-- Frame product select (only when not own frame) -->
        <div v-if="!ownFrame">
            <Label for="frame_product">Seleccionar montura</Label>
            <select
                id="frame_product"
                :value="frame?.product_id ?? ''"
                class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                @change="onFrameSelect"
            >
                <option value="">— Ninguno —</option>
                <option
                    v-for="product in frameProducts"
                    :key="product.id"
                    :value="product.id"
                >
                    {{ product.name }}
                </option>
            </select>
        </div>
    </div>
</template>
