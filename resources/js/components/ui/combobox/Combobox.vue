<script setup lang="ts">
import { Check, ChevronsUpDown, Loader2 } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { cn } from '@/lib/utils';

export interface ComboboxOption {
    value: number;
    label: string;
}

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    id?: string;
    items: ComboboxOption[];
    modelValue: number | null;
    /** Label of the currently selected value, shown when the input is not focused. */
    modelLabel?: string;
    loading?: boolean;
    placeholder?: string;
    emptyText?: string;
    class?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [number | null];
    search: [string];
}>();
</script>

<template>
    <ComboboxRoot
        :model-value="modelValue ?? undefined"
        ignore-filter
        @update:model-value="
            (value) => emit('update:modelValue', (value as number) ?? null)
        "
    >
        <ComboboxAnchor
            :class="
                cn(
                    'border-input focus-within:border-ring focus-within:ring-ring/50 dark:bg-input/30 flex h-9 w-full items-center gap-2 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] focus-within:ring-[3px]',
                    props.class,
                )
            "
        >
            <ComboboxInput
                :id="id"
                :display-value="() => modelLabel ?? ''"
                :placeholder="placeholder"
                class="placeholder:text-muted-foreground w-full bg-transparent outline-none"
                @input="
                    (event: Event) =>
                        emit('search', (event.target as HTMLInputElement).value)
                "
            />
            <Loader2
                v-if="loading"
                class="text-muted-foreground size-4 shrink-0 animate-spin"
            />
            <ComboboxTrigger v-else>
                <ChevronsUpDown class="text-muted-foreground size-4 shrink-0" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                class="bg-popover text-popover-foreground relative z-50 max-h-64 w-(--reka-combobox-trigger-width) overflow-x-hidden overflow-y-auto rounded-md border shadow-md"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxEmpty
                        class="text-muted-foreground py-2 text-center text-sm"
                    >
                        {{ emptyText }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="item in items"
                        :key="item.value"
                        :value="item.value"
                        :text-value="item.label"
                        class="focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                    >
                        {{ item.label }}
                        <ComboboxItemIndicator
                            class="absolute right-2 flex items-center"
                        >
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
