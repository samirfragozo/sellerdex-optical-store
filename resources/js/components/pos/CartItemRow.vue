<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import type { ProductProp } from '@/composables/useLensCatalog';
import type { LooseProduct } from '@/composables/usePosCart';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    item: LooseProduct;
    products: ProductProp[];
}>();

const emit = defineEmits<{
    'update:item': [LooseProduct];
    remove: [];
}>();

function onProductSelect(value: string): void {
    const id = value ? parseInt(value, 10) : null;
    const product = props.products.find((p) => p.id === id);

    emit('update:item', {
        ...props.item,
        product_id: id,
        description: product ? product.name : props.item.description,
        unit_price: product ? product.price : props.item.unit_price,
    });
}
</script>

<template>
    <div
        data-testid="cart-product-row"
        class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_2fr_4rem_5rem_2rem] sm:items-center"
    >
        <select
            :value="item.product_id ?? ''"
            class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
            @change="
                onProductSelect(($event.target as HTMLSelectElement).value)
            "
        >
            <option value="">{{ trans('app.pos.none_option') }}</option>
            <option
                v-for="product in products"
                :key="product.id"
                :value="product.id"
            >
                {{ product.name }}
            </option>
        </select>

        <Input
            :model-value="item.description"
            class="w-full"
            :placeholder="trans('app.fields.description')"
            @update:model-value="
                (value) =>
                    emit('update:item', { ...item, description: String(value) })
            "
        />

        <Input
            :model-value="item.quantity"
            type="number"
            min="1"
            class="w-full text-right"
            @update:model-value="
                (value) =>
                    emit('update:item', { ...item, quantity: Number(value) })
            "
        />

        <Input
            :model-value="item.unit_price"
            type="number"
            min="0"
            class="w-full text-right"
            @update:model-value="
                (value) =>
                    emit('update:item', { ...item, unit_price: Number(value) })
            "
        />

        <button
            type="button"
            class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
            @click="emit('remove')"
        >
            <Trash2 class="size-4" />
        </button>
    </div>
</template>
