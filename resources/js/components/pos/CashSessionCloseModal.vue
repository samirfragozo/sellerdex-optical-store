<script setup lang="ts">
import { ref } from 'vue';
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
import { useCashRegisterSession } from '@/composables/useCashRegisterSession';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { close, preview } from '@/routes/pos/cash-sessions';

const { trans } = useTranslations();
const { session, closeModalOpen, onSessionClosed } = useCashRegisterSession();

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

type Step = 'count' | 'confirm';

const step = ref<Step>('count');
const countedCash = ref(0);
const notes = ref('');
const expectedCash = ref(0);
const openingCash = ref(0);
const error = ref<string | null>(null);
const submitting = ref(false);

function difference(): number {
    return countedCash.value - expectedCash.value;
}

function reset(): void {
    step.value = 'count';
    countedCash.value = 0;
    notes.value = '';
    error.value = null;
}

function close_(): void {
    closeModalOpen.value = false;
    reset();
}

async function goToConfirm(): Promise<void> {
    if (session.value === null) {
        return;
    }

    submitting.value = true;
    error.value = null;

    try {
        const response = await csrfFetch(preview.url(session.value.id));

        if (!response.ok) {
            const body = (await response.json()) as { message: string };
            error.value = body.message;

            return;
        }

        const body = (await response.json()) as {
            opening_cash: number;
            expected_cash: number;
        };
        openingCash.value = body.opening_cash;
        expectedCash.value = body.expected_cash;
        step.value = 'confirm';
    } finally {
        submitting.value = false;
    }
}

async function confirmClose(): Promise<void> {
    if (session.value === null) {
        return;
    }

    if (difference() !== 0 && notes.value.trim() === '') {
        error.value = trans('app.pos.cash_session.notes_required');

        return;
    }

    submitting.value = true;
    error.value = null;

    try {
        const response = await csrfFetch(close.url(session.value.id), {
            method: 'POST',
            body: JSON.stringify({
                closed_cash: countedCash.value,
                notes: notes.value || null,
            }),
        });

        if (!response.ok) {
            const body = (await response.json()) as { message: string };
            error.value = body.message;

            return;
        }

        onSessionClosed();
        reset();
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="closeModalOpen" @update:open="(open) => !open && close_()">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{
                    trans('app.pos.cash_session.close_title')
                }}</DialogTitle>
            </DialogHeader>

            <template v-if="step === 'count'">
                <p class="text-sm text-muted-foreground">
                    {{ trans('app.pos.cash_session.count_notice') }}
                </p>

                <div>
                    <Label for="counted_cash">{{
                        trans('app.pos.cash_session.counted_cash')
                    }}</Label>
                    <Input
                        id="counted_cash"
                        v-model.number="countedCash"
                        type="number"
                        min="0"
                        class="mt-1 w-full text-right"
                    />
                    <InputError class="mt-1" :message="error ?? undefined" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        :disabled="submitting"
                        @click="goToConfirm"
                    >
                        {{ trans('app.pos.cash_session.continue_action') }}
                    </Button>
                </DialogFooter>
            </template>

            <template v-else>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            {{ trans('app.pos.cash_session.opening_amount') }}
                        </dt>
                        <dd>{{ formatCOP(openingCash) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            {{ trans('app.pos.cash_session.expected_cash') }}
                        </dt>
                        <dd>{{ formatCOP(expectedCash) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            {{ trans('app.pos.cash_session.counted_cash') }}
                        </dt>
                        <dd>{{ formatCOP(countedCash) }}</dd>
                    </div>
                    <div class="flex justify-between font-medium">
                        <dt>
                            {{ trans('app.pos.cash_session.difference') }}
                        </dt>
                        <dd
                            :class="
                                difference() === 0
                                    ? 'text-green-600 dark:text-green-500'
                                    : 'text-amber-600 dark:text-amber-500'
                            "
                        >
                            {{ formatCOP(difference()) }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="difference() !== 0"
                    class="text-sm text-amber-600 dark:text-amber-500"
                >
                    {{ trans('app.pos.cash_session.difference_notice') }}
                </p>

                <div>
                    <Label for="close_notes">{{
                        trans('app.fields.notes')
                    }}</Label>
                    <textarea
                        id="close_notes"
                        v-model="notes"
                        rows="2"
                        class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        :placeholder="trans('app.pos.notes_placeholder')"
                    ></textarea>
                    <InputError class="mt-1" :message="error ?? undefined" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="submitting"
                        @click="step = 'count'"
                    >
                        {{ trans('app.pos.back') }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="submitting"
                        @click="confirmClose"
                    >
                        {{ trans('app.pos.cash_session.confirm_close_action') }}
                    </Button>
                </DialogFooter>
            </template>
        </DialogContent>
    </Dialog>
</template>
