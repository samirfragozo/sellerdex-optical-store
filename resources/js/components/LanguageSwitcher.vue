<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update as updateLocale } from '@/routes/locale';

const page = usePage();
const currentLocale = computed(() => page.props.locale);

const locales = [
    { value: 'es', label: 'ES' },
    { value: 'en', label: 'EN' },
] as const;

function switchLocale(locale: string): void {
    if (locale === currentLocale.value) {
        return;
    }

    router.post(
        updateLocale.url({ locale }),
        {},
        { preserveScroll: true, preserveState: false },
    );
}
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, label } in locales"
            :key="value"
            type="button"
            @click="switchLocale(value)"
            :class="[
                'rounded-md px-2 py-1 text-xs font-medium transition-colors',
                currentLocale === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            {{ label }}
        </button>
    </div>
</template>
