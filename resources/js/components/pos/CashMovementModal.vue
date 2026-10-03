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
import { store } from '@/routes/pos/cash-sessions/movements';

const { trans } = useTranslations();
const { session, movementModalOpen } = useCashRegisterSession();

const types = ['income', 'withdrawal'] as const;

const type = ref<(typeof types)[number]>('withdrawal');
const amount = ref<number | ''>('');
const reason = ref('');
const errors = ref<Record<string, string>>({});
const submitting = ref(false);

function close(): void {
    movementModalOpen.value = false;
    amount.value = '';
    reason.value = '';
    errors.value = {};
}

async function submit(): Promise<void> {
    if (session.value === null) {
        return;
    }

    submitting.value = true;
    errors.value = {};

    try {
        const response = await csrfFetch(store.url(session.value.id), {
            method: 'POST',
            body: JSON.stringify({
                type: type.value,
                amount: amount.value,
                reason: reason.value,
            }),
        });

        if (response.ok) {
            close();

            return;
        }

        const body = (await response.json()) as {
            message: string;
            errors?: Record<string, string[]>;
        };
        errors.value = Object.fromEntries(
            Object.entries(body.errors ?? { general: [body.message] }).map(
                ([key, messages]) => [key, messages[0]],
            ),
        );
    } catch {
        errors.value = {
            general: trans('app.pos.checkout.unexpected_error'),
        };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="movementModalOpen" @update:open="(open) => !open && close()">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{
                    trans('app.pos.cash_session.movement')
                }}</DialogTitle>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex gap-2" role="group">
                    <Button
                        v-for="option in types"
                        :key="option"
                        type="button"
                        size="sm"
                        :variant="type === option ? 'default' : 'outline'"
                        :aria-pressed="type === option"
                        @click="type = option"
                    >
                        {{ trans(`app.pos.cash_session.movement_${option}`) }}
                    </Button>
                </div>

                <div>
                    <Label for="cash_movement_amount">{{
                        trans('app.pos.cash_session.movement_amount')
                    }}</Label>
                    <Input
                        id="cash_movement_amount"
                        v-model.number="amount"
                        type="number"
                        min="1"
                        required
                        class="mt-1 w-full text-right"
                        :aria-describedby="
                            errors.amount
                                ? 'cash_movement_amount_error'
                                : undefined
                        "
                    />
                    <InputError
                        id="cash_movement_amount_error"
                        class="mt-1"
                        :message="errors.amount"
                    />
                </div>

                <div>
                    <Label for="cash_movement_reason">{{
                        trans('app.pos.cash_session.movement_reason')
                    }}</Label>
                    <Input
                        id="cash_movement_reason"
                        v-model="reason"
                        maxlength="255"
                        required
                        class="mt-1 w-full"
                        :aria-describedby="
                            errors.reason
                                ? 'cash_movement_reason_error'
                                : undefined
                        "
                    />
                    <InputError
                        id="cash_movement_reason_error"
                        class="mt-1"
                        :message="
                            errors.reason ?? errors.type ?? errors.general
                        "
                    />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="submitting">
                        {{ trans('app.pos.cash_session.movement_submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
