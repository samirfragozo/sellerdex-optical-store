<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';

defineProps<{ title: string; open: boolean; summary?: string }>();
defineEmits<{ toggle: [] }>();
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between p-4 text-left"
            @click="$emit('toggle')"
        >
            <span class="text-base font-semibold">{{ title }}</span>
            <span class="flex items-center gap-2">
                <span
                    v-if="!open && summary"
                    class="text-sm text-muted-foreground"
                    >{{ summary }}</span
                >
                <ChevronDown
                    class="size-4 transition-transform"
                    :class="open ? 'rotate-180' : ''"
                />
            </span>
        </button>
        <div v-show="open" class="border-t border-border p-4">
            <slot />
        </div>
    </div>
</template>
