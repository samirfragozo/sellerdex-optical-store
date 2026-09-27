<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { ReadinessIssue } from '@/types/global';

const props = defineProps<{ issues: ReadinessIssue[] }>();
const { trans } = useTranslations();

const STORAGE_KEY = 'readiness-banner-hidden';

function readHidden(): boolean {
    try {
        return sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

const hidden = ref(readHidden());
const warnings = computed(() =>
    props.issues.filter((i) => i.severity === 'warning'),
);

function hide(): void {
    hidden.value = true;

    try {
        sessionStorage.setItem(STORAGE_KEY, '1');
    } catch {
        // Storage can be unavailable (private mode); hiding still works for this page.
    }
}
</script>

<template>
    <div v-if="warnings.length > 0 && !hidden" class="flex flex-col gap-2 p-2">
        <Alert v-for="issue in warnings" :key="issue.key" role="status">
            <TriangleAlert class="size-4" aria-hidden="true" />
            <AlertDescription
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <span>{{ issue.message }}</span>
                <span class="flex gap-2">
                    <Button
                        v-if="issue.url"
                        as="a"
                        :href="issue.url"
                        variant="link"
                        size="sm"
                    >
                        {{ trans('app.readiness.fix') }}
                    </Button>
                    <span v-else class="self-center">
                        {{ trans('app.readiness.ask_admin') }}
                    </span>
                    <Button variant="ghost" size="sm" @click="hide">
                        {{ trans('app.readiness.dismiss') }}
                    </Button>
                </span>
            </AlertDescription>
        </Alert>
    </div>
</template>
