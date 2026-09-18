<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Pagination from '@/components/pos/Pagination.vue';
import ProductCard from '@/components/pos/ProductCard.vue';
import VariantPickerDialog from '@/components/pos/VariantPickerDialog.vue';
import { Input } from '@/components/ui/input';
import type {
    PaginatedProducts,
    ProductProp,
    VariantOption,
} from '@/composables/useLensCatalog';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    products: PaginatedProducts;
    categories: { key: string | null; name: string }[];
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    'select-lens-category': [];
    'add-product': [ProductProp];
    'add-resolved-product': [
        { id: number; name: string; price: number; tax_rate?: number },
    ];
}>();

const search = ref('');
const selectedCategoryKey = ref<string | null>(null);
let debounceHandle: ReturnType<typeof setTimeout> | null = null;

const categoryOptions = computed(() => [
    { key: null, name: trans('app.pos.catalog.all_categories') },
    ...props.categories.map((c) => ({ key: c.key, name: c.name })),
]);

// A product with option groups (e.g. a frame with type/material variants)
// can't be click-to-added directly — its own price is a placeholder, only a
// resolved variant is sellable — so it opens the variant picker instead.
const variantPickerProduct = ref<ProductProp | null>(null);

function reload(page = 1): void {
    router.reload({
        only: ['products'],
        data: {
            search: search.value || undefined,
            category: selectedCategoryKey.value || undefined,
            page,
        },
        preserveState: true,
        preserveScroll: true,
    });
}

watch(search, () => {
    if (debounceHandle) {
        clearTimeout(debounceHandle);
    }

    debounceHandle = setTimeout(() => reload(1), 400);
});

watch(selectedCategoryKey, () => reload(1));

function onProductClick(product: ProductProp): void {
    if (product.option_groups.length > 0) {
        variantPickerProduct.value = product;

        return;
    }

    emit('add-product', product);
}

function onVariantSelected(option: VariantOption): void {
    emit('add-resolved-product', {
        id: option.value,
        name: option.label,
        price: option.unit_price,
        tax_rate: variantPickerProduct.value?.tax_rate,
    });
}

// Lens products always carry option groups, so the general catalog query
// excludes them entirely (see ProductCatalog::index) — filtering by this
// category would only ever show an empty grid. Route it to the armado flow
// instead of selecting it as a filter.
function onCategoryClick(key: string | null): void {
    if (key === 'lens') {
        emit('select-lens-category');

        return;
    }

    selectedCategoryKey.value = key;
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div
            class="border-b border-sidebar-border/70 p-3 dark:border-sidebar-border"
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
                v-for="category in categoryOptions"
                :key="category.key ?? 'all'"
                type="button"
                :class="[
                    'shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/50',
                    selectedCategoryKey === category.key
                        ? 'bg-primary text-primary-foreground'
                        : 'border border-input bg-transparent hover:bg-accent',
                ]"
                @click="onCategoryClick(category.key)"
            >
                {{ category.name }}
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-3">
            <div
                v-if="products.data.length > 0"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="product in products.data"
                    :key="product.id"
                    :product="product"
                    @click="onProductClick(product)"
                />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                {{ trans('app.pos.catalog.empty') }}
            </p>
        </div>

        <VariantPickerDialog
            :open="variantPickerProduct !== null"
            :product="variantPickerProduct"
            @update:open="(open) => !open && (variantPickerProduct = null)"
            @select="onVariantSelected"
        />

        <Pagination
            :current-page="products.meta.current_page"
            :last-page="products.meta.last_page"
            :from="products.meta.from ?? 0"
            :to="products.meta.to ?? 0"
            :total="products.meta.total"
            @turn-page="reload"
        />
    </div>
</template>
