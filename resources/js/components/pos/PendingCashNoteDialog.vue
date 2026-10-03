<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import CashCountResult from '@/components/pos/CashCountResult.vue';
import CashNoteForm from '@/components/pos/CashNoteForm.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import type { CashCountLine } from '@/types/global';

defineProps<{ open: boolean; sessionId: number; counts: CashCountLine[] }>();

const { trans } = useTranslations();

function saved(): void {
    router.reload({ only: ['pendingCashNote'] });
}
</script>

<template>
    <Dialog :open="open">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-md"
            :show-close-button="false"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
        >
            <DialogHeader>
                <DialogTitle>{{
                    trans('app.pos.cash_session.pending_note_title')
                }}</DialogTitle>
            </DialogHeader>
            <CashCountResult :counts="counts" />
            <CashNoteForm :session-id="sessionId" @saved="saved" />
        </DialogContent>
    </Dialog>
</template>
