<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    from: number;
    to: number;
    total: number;
}>();

const emit = defineEmits<{
    'turn-page': [number];
}>();

function summary(): string {
    return trans('app.pos.catalog.pagination_summary')
        .replace(':from', String(props.from))
        .replace(':to', String(props.to))
        .replace(':total', String(props.total));
}

function pageNumbers(): number[] {
    const start = Math.max(1, props.currentPage - 2);
    const end = Math.min(props.lastPage, start + 4);

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
}
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="flex items-center justify-between gap-2 border-t border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
    >
        <span class="text-muted-foreground">{{ summary() }}</span>
        <div class="flex items-center gap-1">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="currentPage <= 1"
                @click="emit('turn-page', currentPage - 1)"
            >
                {{ trans('app.pos.catalog.pagination_prev') }}
            </Button>
            <Button
                v-for="page in pageNumbers()"
                :key="page"
                type="button"
                :variant="page === currentPage ? 'default' : 'outline'"
                size="sm"
                @click="emit('turn-page', page)"
            >
                {{ page }}
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="currentPage >= lastPage"
                @click="emit('turn-page', currentPage + 1)"
            >
                {{ trans('app.pos.catalog.pagination_next') }}
            </Button>
        </div>
    </div>
</template>
