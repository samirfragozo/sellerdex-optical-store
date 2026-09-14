<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Pagination from '@/components/pos/Pagination.vue';
import ProductCard from '@/components/pos/ProductCard.vue';
import { Input } from '@/components/ui/input';
import type {
    PaginatedProducts,
    ProductProp,
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
}>();

const search = ref('');
const selectedCategoryKey = ref<string | null>(null);
let debounceHandle: ReturnType<typeof setTimeout> | null = null;

const categoryOptions = computed(() => [
    { key: null, name: trans('app.pos.catalog.all_categories') },
    ...props.categories.map((c) => ({ key: c.key, name: c.name })),
]);

// Products with option groups (e.g. frames with a color option) require a
// dedicated selection flow and must never be click-to-added from this grid.
const filteredProducts = computed(() =>
    props.products.data.filter((product) => product.option_groups.length === 0),
);

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
    emit('add-product', product);
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
                v-for="category in categoryOptions"
                :key="category.key ?? 'all'"
                type="button"
                :class="[
                    'shrink-0 rounded-md px-3 py-1.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/50',
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
