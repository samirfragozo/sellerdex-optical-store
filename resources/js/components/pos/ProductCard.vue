<script setup lang="ts">
import type { ProductProp } from '@/composables/useLensCatalog';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

defineProps<{
    product: ProductProp;
}>();

defineEmits<{
    click: [];
}>();

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}
</script>

<template>
    <button
        type="button"
        class="flex flex-col items-start gap-1 rounded-xl border border-sidebar-border/70 bg-white p-3 text-left transition-colors hover:border-primary hover:bg-accent dark:border-sidebar-border dark:bg-zinc-900"
        @click="$emit('click')"
    >
        <span class="text-xs text-muted-foreground">{{
            product.category_name ?? trans('app.pos.catalog.uncategorized')
        }}</span>
        <span class="line-clamp-2 text-sm font-medium">{{ product.name }}</span>
        <span class="mt-auto text-sm font-semibold tabular-nums">{{
            formatCOP(product.price)
        }}</span>
        <span
            v-if="product.is_stockable && (product.stock ?? 0) <= 0"
            class="text-xs font-medium text-destructive"
        >
            {{ trans('app.pos.catalog.out_of_stock') }}
        </span>
    </button>
</template>
