<script setup lang="ts">
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { ProductProp, VariantOption } from '@/composables/useLensCatalog';
import { resolveVariantOptions } from '@/composables/useLensCatalog';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    open: boolean;
    product: ProductProp | null;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    select: [VariantOption];
}>();

const options = computed<VariantOption[]>(() =>
    props.product ? resolveVariantOptions(props.product) : [],
);

function onSelect(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const id = parseInt(value, 10);
    const option = options.value.find((o) => o.value === id);

    if (option) {
        emit('select', option);
        emit('update:open', false);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ product?.name }}</DialogTitle>
            </DialogHeader>

            <div>
                <Label for="variant_picker">{{
                    trans('app.pos.frame_form.select_frame')
                }}</Label>
                <select
                    id="variant_picker"
                    class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                    @change="onSelect"
                >
                    <option value="">
                        {{ trans('app.pos.select_option') }}
                    </option>
                    <option
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </DialogContent>
    </Dialog>
</template>
