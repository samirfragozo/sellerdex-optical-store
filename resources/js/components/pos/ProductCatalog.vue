<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ProductProp } from '@/composables/useLensCatalog';
import { useTranslations } from '@/composables/useTranslations';
import ProductCard from '@/components/pos/ProductCard.vue';
import { Input } from '@/components/ui/input';

const { trans } = useTranslations();

const props = defineProps<{
    products: ProductProp[];
}>();

const emit = defineEmits<{
    'select-lens-category': [];
    'add-product': [ProductProp];
}>();

const search = ref('');
const selectedCategoryKey = ref<string | null>(null);

interface CategoryOption {
    key: string | null;
    name: string;
}

const categories = computed<CategoryOption[]>(() => {
    const seen = new Map<string, string>();
    for (const p of props.products) {
        if (p.category_key && !seen.has(p.category_key)) {
            seen.set(p.category_key, p.category_name ?? p.category_key);
        }
    }

    return [
        { key: null, name: trans('app.pos.catalog.all_categories') },
        ...Array.from(seen, ([key, name]) => ({ key, name })),
    ];
});

const filteredProducts = computed<ProductProp[]>(() => {
    const term = search.value.trim().toLowerCase();

    return props.products.filter((p) => {
        const matchesCategory =
            selectedCategoryKey.value === null ||
            p.category_key === selectedCategoryKey.value;
        const matchesSearch =
            term === '' || p.name.toLowerCase().includes(term);

        return matchesCategory && matchesSearch;
    });
});

function onProductClick(product: ProductProp): void {
    if (product.category_key === 'lens') {
        emit('select-lens-category');

        return;
    }

    emit('add-product', product);
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div
            class="border-b border-sidebar-border/70 bg-white p-3 dark:border-sidebar-border dark:bg-zinc-900"
        >
            <Input
                v-model="search"
                :placeholder="trans('app.pos.catalog.search_placeholder')"
                class="w-full"
            />
        </div>

        <div
            class="flex gap-2 overflow-x-auto border-b border-sidebar-border/70 p-3 dark:border-sidebar-border"
        >
            <button
                v-for="category in categories"
                :key="category.key ?? 'all'"
                type="button"
                :class="[
                    'shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                    selectedCategoryKey === category.key
                        ? 'bg-primary text-primary-foreground'
                        : 'border border-input bg-transparent hover:bg-accent',
                ]"
                @click="selectedCategoryKey = category.key"
            >
                {{ category.name }}
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-3">
            <div
                v-if="filteredProducts.length > 0"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="product in filteredProducts"
                    :key="product.id"
                    :product="product"
                    @click="onProductClick(product)"
                />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                {{ trans('app.pos.catalog.empty') }}
            </p>
        </div>
    </div>
</template>
