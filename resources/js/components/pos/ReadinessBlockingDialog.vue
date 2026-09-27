<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import type { ReadinessIssue } from '@/types/global';

defineProps<{ issues: ReadinessIssue[]; title: string }>();
const open = defineModel<boolean>('open', { required: true });
const { trans } = useTranslations();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription class="sr-only">{{
                    title
                }}</DialogDescription>
            </DialogHeader>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="issue in issues"
                    :key="issue.key"
                    class="flex flex-col gap-1 text-sm"
                >
                    <span>{{ issue.message }}</span>
                    <Button
                        v-if="issue.url"
                        as="a"
                        :href="issue.url"
                        variant="outline"
                        size="sm"
                        class="self-start"
                    >
                        {{ trans('app.readiness.fix') }}
                    </Button>
                    <span v-else class="text-muted-foreground">
                        {{ trans('app.readiness.ask_admin') }}
                    </span>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
