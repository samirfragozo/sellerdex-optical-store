<script setup lang="ts">
import { Download, Eye, FileText, X } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';
import type { CreatedSale } from '@/types/global';

const { trans } = useTranslations();

defineProps<{
    sale: CreatedSale;
}>();

defineEmits<{
    dismiss: [];
}>();
</script>

<template>
    <div
        class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/30"
    >
        <div class="mb-3 flex items-start justify-between gap-2">
            <p
                class="text-base font-semibold text-green-800 dark:text-green-300"
            >
                {{ trans('app.pos.created').replace(':number', sale.number) }}
            </p>
            <button
                type="button"
                class="text-green-700 hover:text-green-900 dark:text-green-400"
                @click="$emit('dismiss')"
            >
                <X class="size-4" />
            </button>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                :href="sale.invoice_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <Eye class="size-4" />
                {{ trans('app.documents.print_invoice') }}
            </a>
            <a
                :href="sale.invoice_pdf_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <Download class="size-4" />
                {{ trans('app.documents.download_invoice') }}
            </a>
            <a
                v-if="sale.formula_url"
                :href="sale.formula_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <FileText class="size-4" />
                {{ trans('app.documents.print_formula') }}
            </a>
        </div>
    </div>
</template>
