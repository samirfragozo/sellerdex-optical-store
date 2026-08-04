<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/appearance';

const { trans } = useTranslations();

setLayoutProps({
    breadcrumbs: [
        {
            title: trans('settings.appearance.breadcrumb'),
            href: edit(),
        },
    ],
});
</script>

<template>
    <Head :title="trans('settings.appearance.title')" />

    <h1 class="sr-only">{{ trans('settings.appearance.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="trans('settings.appearance.title')"
            :description="trans('settings.appearance.description')"
        />
        <AppearanceTabs />
    </div>
</template>
