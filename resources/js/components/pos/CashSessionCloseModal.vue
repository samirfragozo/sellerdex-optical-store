<script lang="ts">
export const COP_DENOMINATIONS = [
    100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50,
];
</script>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import CashCountResult from '@/components/pos/CashCountResult.vue';
import CashNoteForm from '@/components/pos/CashNoteForm.vue';
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
import type { CashCountLine } from '@/types/global';

interface PreviewMethod {
    payment_method_id: number;
    name: string;
    is_cash: boolean;
    expected: number | null;
}

interface CloseResult {
    counts: CashCountLine[];
    requires_note: boolean;
}

const { trans } = useTranslations();
const { session, closeModalOpen, onSessionClosed } = useCashRegisterSession();
const page = usePage();

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

function toAmount(value: number | ''): number {
    return Number(value) || 0;
}

function firstErrors(body: {
    message?: string;
    errors?: Record<string, string[]>;
}): Record<string, string> {
    const errors = Object.fromEntries(
        Object.entries(body.errors ?? { general: [body.message ?? ''] }).map(
            ([key, messages]) => [
                key.startsWith('counts') ? 'counts' : key,
                messages[0],
            ],
        ),
    );

    return {
        ...errors,
        general: errors.counts ?? errors.session ?? errors.general,
    };
}

const methods = ref<PreviewMethod[]>([]);
const byDenomination = ref(false);
const cashTotal = ref<number | ''>('');
const quantities = ref<Record<number, number | ''>>({});
const otherAmounts = ref<Record<number, number | ''>>({});
const cashLeft = ref<number | ''>(0);
const cashLeftEdited = ref(false);
const notes = ref('');
const errors = ref<Record<string, string>>({});
const submitting = ref(false);
const result = ref<CloseResult | null>(null);
const noteSaved = ref(false);

const cashMethod = computed(() => methods.value.find((m) => m.is_cash));
const otherMethods = computed(() => methods.value.filter((m) => !m.is_cash));
const countedCash = computed(() =>
    byDenomination.value
        ? COP_DENOMINATIONS.reduce(
              (sum, value) =>
                  sum + value * toAmount(quantities.value[value] ?? 0),
              0,
          )
        : toAmount(cashTotal.value),
);
const suggestedFloat = computed(() => page.props.suggestedOpeningCash ?? 0);
const countComplete = computed(
    () =>
        methods.value.length > 0 &&
        (byDenomination.value || cashTotal.value !== '') &&
        otherMethods.value.every(
            (m) => (otherAmounts.value[m.payment_method_id] ?? '') !== '',
        ),
);
const mustSaveNote = computed(
    () => result.value?.requires_note === true && !noteSaved.value,
);

function reset(): void {
    byDenomination.value = false;
    cashTotal.value = '';
    quantities.value = {};
    otherAmounts.value = {};
    cashLeft.value = 0;
    cashLeftEdited.value = false;
    methods.value = [];
    notes.value = '';
    errors.value = {};
    result.value = null;
    noteSaved.value = false;
}

// Until the cashier edits it, the float to leave follows the count (capped at the suggestion).
watch(countedCash, (counted) => {
    if (!cashLeftEdited.value) {
        cashLeft.value = Math.min(counted, suggestedFloat.value);
    }
});

watch(closeModalOpen, async (open) => {
    if (!open || session.value === null) {
        return;
    }

    reset();

    try {
        const response = await csrfFetch(preview.url(session.value.id));

        if (!response.ok) {
            throw new Error();
        }

        const body = (await response.json()) as {
            methods: PreviewMethod[];
        };
        methods.value = body.methods;
    } catch {
        errors.value = { general: trans('app.pos.checkout.unexpected_error') };
    }
});

function dismiss(): void {
    if (mustSaveNote.value) {
        return;
    }

    if (result.value !== null) {
        onSessionClosed();

        return;
    }

    closeModalOpen.value = false;
}

async function post(url: string, payload: object): Promise<Response | null> {
    submitting.value = true;
    errors.value = {};

    try {
        const response = await csrfFetch(url, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            errors.value = firstErrors(await response.json());

            return null;
        }

        return response;
    } catch {
        errors.value = { general: trans('app.pos.checkout.unexpected_error') };

        return null;
    } finally {
        submitting.value = false;
    }
}

async function submitCount(): Promise<void> {
    if (session.value === null) {
        return;
    }

    const counts: Record<number, number> = Object.fromEntries(
        otherMethods.value.map((m) => [
            m.payment_method_id,
            toAmount(otherAmounts.value[m.payment_method_id] ?? 0),
        ]),
    );

    if (cashMethod.value) {
        counts[cashMethod.value.payment_method_id] = countedCash.value;
    }

    const response = await post(close.url(session.value.id), {
        counts,
        cash_left: toAmount(cashLeft.value),
        notes: notes.value || null,
    });

    if (response !== null) {
        result.value = (await response.json()) as CloseResult;
    }
}
</script>

<template>
    <Dialog :open="closeModalOpen" @update:open="(open) => !open && dismiss()">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-md"
            :show-close-button="!mustSaveNote"
            @escape-key-down="(event) => mustSaveNote && event.preventDefault()"
            @pointer-down-outside="
                (event) => mustSaveNote && event.preventDefault()
            "
        >
            <DialogHeader>
                <DialogTitle>{{
                    trans('app.pos.cash_session.close_title')
                }}</DialogTitle>
            </DialogHeader>

            <form
                v-if="result === null"
                class="flex flex-col gap-4"
                @submit.prevent="submitCount"
            >
                <div v-if="cashMethod" class="flex flex-col gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium">{{
                            cashMethod.name
                        }}</span>
                        <span
                            v-if="cashMethod.expected !== null"
                            class="text-sm text-muted-foreground"
                        >
                            {{ trans('app.pos.cash_session.expected') }}:
                            {{ formatCOP(cashMethod.expected) }}
                        </span>
                    </div>

                    <div class="flex gap-2" role="group">
                        <Button
                            type="button"
                            size="sm"
                            :variant="byDenomination ? 'outline' : 'default'"
                            :aria-pressed="!byDenomination"
                            @click="byDenomination = false"
                        >
                            {{ trans('app.pos.cash_session.count_total') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="byDenomination ? 'default' : 'outline'"
                            :aria-pressed="byDenomination"
                            @click="byDenomination = true"
                        >
                            {{
                                trans(
                                    'app.pos.cash_session.count_by_denomination',
                                )
                            }}
                        </Button>
                    </div>

                    <div v-if="!byDenomination">
                        <Label for="cash_total">{{
                            trans('app.pos.cash_session.counted')
                        }}</Label>
                        <Input
                            id="cash_total"
                            v-model.number="cashTotal"
                            type="number"
                            min="0"
                            step="1"
                            required
                            class="mt-1 w-full text-right"
                        />
                    </div>
                    <div v-else class="grid grid-cols-2 gap-2">
                        <div v-for="value in COP_DENOMINATIONS" :key="value">
                            <Label :for="`denomination_${value}`">{{
                                formatCOP(value)
                            }}</Label>
                            <Input
                                :id="`denomination_${value}`"
                                v-model.number="quantities[value]"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="0"
                                class="mt-1 text-right"
                                :aria-label="
                                    trans(
                                        value >= 2000
                                            ? 'app.pos.cash_session.bills_of'
                                            : 'app.pos.cash_session.coins_of',
                                    ).replace(':amount', formatCOP(value))
                                "
                            />
                        </div>
                        <p class="col-span-2 text-right text-sm font-medium">
                            {{ trans('app.pos.cash_session.counted') }}:
                            {{ formatCOP(countedCash) }}
                        </p>
                    </div>
                </div>

                <div
                    v-for="method in otherMethods"
                    :key="method.payment_method_id"
                >
                    <Label :for="`count_${method.payment_method_id}`">
                        {{ method.name }}
                        <span
                            v-if="method.expected !== null"
                            class="font-normal text-muted-foreground"
                        >
                            ({{ trans('app.pos.cash_session.expected') }}:
                            {{ formatCOP(method.expected) }})
                        </span>
                    </Label>
                    <Input
                        :id="`count_${method.payment_method_id}`"
                        v-model.number="otherAmounts[method.payment_method_id]"
                        type="number"
                        min="0"
                        step="1"
                        required
                        class="mt-1 w-full text-right"
                    />
                </div>

                <div>
                    <Label for="cash_left">{{
                        trans('app.pos.cash_session.cash_left')
                    }}</Label>
                    <Input
                        id="cash_left"
                        v-model.number="cashLeft"
                        type="number"
                        min="0"
                        step="1"
                        @input="cashLeftEdited = true"
                        class="mt-1 w-full text-right"
                        :aria-describedby="
                            errors.cash_left
                                ? 'cash_left_error'
                                : 'cash_left_hint'
                        "
                    />
                    <p
                        id="cash_left_hint"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ trans('app.pos.cash_session.cash_left_hint') }}
                    </p>
                    <InputError
                        id="cash_left_error"
                        class="mt-1"
                        :message="errors.cash_left"
                    />
                </div>

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
                    <InputError class="mt-1" :message="errors.notes" />
                </div>

                <InputError :message="errors.general" />

                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="submitting || !countComplete"
                    >
                        {{ trans('app.pos.cash_session.close_action') }}
                    </Button>
                </DialogFooter>
                <p
                    v-if="methods.length > 0 && !countComplete"
                    class="text-right text-xs text-muted-foreground"
                >
                    {{ trans('app.pos.cash_session.count_incomplete') }}
                </p>
            </form>

            <div v-else class="flex flex-col gap-4">
                <CashCountResult :counts="result.counts" />

                <CashNoteForm
                    v-if="session && result.requires_note && !noteSaved"
                    :session-id="session.id"
                    @saved="noteSaved = true"
                />

                <DialogFooter>
                    <Button
                        type="button"
                        :disabled="mustSaveNote"
                        @click="dismiss"
                    >
                        {{ trans('app.pos.cash_session.done') }}
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
