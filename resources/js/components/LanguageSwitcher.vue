<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import { update as updateLocale } from '@/routes/locale';

const page = usePage();
const { trans } = useTranslations();
const currentLocale = computed(() => page.props.locale);

const locales = [
    { value: 'es', label: 'ES' },
    { value: 'en', label: 'EN' },
] as const;

const otherLocales = computed(() =>
    locales.filter((locale) => locale.value !== currentLocale.value),
);

const currentLabel = computed(
    () => locales.find((locale) => locale.value === currentLocale.value)?.label,
);

function switchLocale(locale: string): void {
    router.post(
        updateLocale.url({ locale }),
        {},
        { preserveScroll: true, preserveState: false },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            :title="trans(`app.languages.${currentLocale}`)"
            class="flex size-8 items-center justify-center rounded-lg text-md"
        >
            {{ currentLabel }}
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="locale in otherLocales"
                :key="locale.value"
                class="gap-2 w-56"
                @click="switchLocale(locale.value)"
            >
                <span
                    class="flex size-5 items-center justify-center rounded-lg text-md"
                >
                    {{ locale.label }}
                </span>
                <span>
                    {{ trans(`app.languages.${locale.value}`) }}
                </span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
