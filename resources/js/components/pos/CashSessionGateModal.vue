<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { store } from '@/routes/pos/cash-sessions';
import type { CashRegisterSession } from '@/types/global';

const { trans } = useTranslations();

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    opened: [CashRegisterSession];
}>();

const page = usePage();
const suggested = computed(() => page.props.suggestedOpeningCash ?? 0);
const openingCash = ref(suggested.value);

// Each time the gate shows (and when a fresh suggestion lands) start from the last close's float.
watch(
    () => [props.open, suggested.value] as const,
    ([open, value]) => {
        if (open) {
            openingCash.value = value;
        }
    },
);
const error = ref<string | null>(null);
const submitting = ref(false);

async function submit(): Promise<void> {
    submitting.value = true;
    error.value = null;

    try {
        const response = await csrfFetch(store.url(), {
            method: 'POST',
            body: JSON.stringify({ opening_cash: openingCash.value }),
        });

        if (!response.ok) {
            const body = (await response.json()) as { message: string };
            error.value = body.message;

            return;
        }

        emit('opened', (await response.json()) as CashRegisterSession);
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="open">
        <DialogContent
            class="sm:max-w-sm"
            :show-close-button="false"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
        >
            <DialogHeader>
                <DialogTitle>{{
                    trans('app.pos.cash_session.open_title')
                }}</DialogTitle>
            </DialogHeader>

            <p class="text-sm text-muted-foreground">
                {{ trans('app.pos.cash_session.open_notice') }}
            </p>

            <div>
                <Label for="opening_cash">{{
                    trans('app.pos.cash_session.opening_cash')
                }}</Label>
                <Input
                    id="opening_cash"
                    v-model.number="openingCash"
                    type="number"
                    min="0"
                    class="mt-1 w-full text-right"
                />
                <p
                    v-if="suggested > 0"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ trans('app.pos.cash_session.suggested_hint') }}
                </p>
                <InputError class="mt-1" :message="error ?? undefined" />
            </div>

            <DialogFooter>
                <Button type="button" :disabled="submitting" @click="submit">
                    {{ trans('app.pos.cash_session.open_action') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
