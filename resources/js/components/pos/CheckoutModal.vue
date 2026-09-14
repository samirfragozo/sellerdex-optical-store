<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import NumericKeypad from '@/components/pos/NumericKeypad.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { usePosCheckout } from '@/composables/usePosCheckout';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const QUICK_BILLS = [2000, 5000, 10000, 20000, 50000, 100000];

const props = defineProps<{
    open: boolean;
    paymentMethods: { id: number; name: string; surcharge_percent: number }[];
    documentTypes: { value: string; label: string }[];
    total: number;
    checkout: ReturnType<typeof usePosCheckout>;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    confirm: [];
}>();

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

function paymentMethodName(id: number | null): string {
    return (
        props.paymentMethods.find((pm) => pm.id === id)?.name ??
        trans('app.pos.select_option')
    );
}

function selectPaymentMethod(paymentMethodId: number): void {
    if (props.checkout.amount.value <= 0) {
        return;
    }

    props.checkout.addPayment(paymentMethodId, props.checkout.amount.value);
    // eslint-disable-next-line vue/no-mutating-props
    props.checkout.amount.value = Math.max(props.checkout.remaining.value, 0);
}

function selectBill(value: number): void {
    // eslint-disable-next-line vue/no-mutating-props
    props.checkout.amount.value = value;
}

// Pre-fill the amount to pay with the outstanding balance every time the
// modal opens, so the cashier can go straight to picking a payment method.
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            // eslint-disable-next-line vue/no-mutating-props
            props.checkout.amount.value = Math.max(
                props.checkout.remaining.value,
                0,
            );
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogScrollContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ trans('app.pos.checkout.title') }}</DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-4">
                <!-- Document type -->
                <div>
                    <Label>{{
                        trans('app.pos.payment_form.document_type')
                    }}</Label>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <label
                            v-for="dt in documentTypes"
                            :key="dt.value"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-accent"
                        >
                            <!-- eslint-disable vue/no-mutating-props -->
                            <input
                                v-model="checkout.documentType.value"
                                type="radio"
                                :value="dt.value"
                                class="size-4 accent-primary"
                            />
                            <!-- eslint-enable vue/no-mutating-props -->
                            {{ dt.label }}
                        </label>
                    </div>
                </div>

                <!-- Amount to pay -->
                <div>
                    <Label for="checkout_amount">{{
                        trans('app.pos.checkout.amount_to_pay')
                    }}</Label>
                    <!-- eslint-disable vue/no-mutating-props -->
                    <Input
                        id="checkout_amount"
                        v-model.number="checkout.amount.value"
                        type="number"
                        min="0"
                        class="mt-1 h-12 text-right text-xl font-semibold"
                    />
                    <!-- eslint-enable vue/no-mutating-props -->
                </div>

                <!-- Payment method buttons -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="pm in paymentMethods"
                        :key="pm.id"
                        type="button"
                        class="rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm font-medium hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="checkout.amount.value <= 0"
                        @click="selectPaymentMethod(pm.id)"
                    >
                        {{ pm.name }}
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <!-- Summary + added payments -->
                    <div
                        class="flex flex-col gap-2 rounded-md border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                trans('app.fields.total')
                            }}</span>
                            <span class="font-medium">{{
                                formatCOP(total)
                            }}</span>
                        </div>

                        <p
                            v-if="checkout.payments.value.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ trans('app.pos.checkout.no_payments') }}
                        </p>
                        <div
                            v-for="(payment, index) in checkout.payments.value"
                            :key="index"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span class="min-w-0 flex-1 truncate">{{
                                paymentMethodName(payment.payment_method_id)
                            }}</span>
                            <span class="shrink-0 font-medium">{{
                                formatCOP(payment.amount)
                            }}</span>
                            <button
                                type="button"
                                class="shrink-0 text-muted-foreground hover:text-destructive"
                                :aria-label="
                                    trans('app.pos.checkout.remove_payment')
                                "
                                @click="checkout.removePayment(index)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-sidebar-border/70 pt-2 text-sm dark:border-sidebar-border"
                        >
                            <span class="text-muted-foreground">{{
                                trans('app.pos.checkout.remaining')
                            }}</span>
                            <span
                                :class="
                                    checkout.remaining.value > 0
                                        ? 'text-destructive'
                                        : 'text-foreground'
                                "
                                class="font-semibold"
                            >
                                {{ formatCOP(checkout.remaining.value) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                trans('app.pos.checkout.change')
                            }}</span>
                            <span class="font-semibold text-primary">{{
                                formatCOP(checkout.change.value)
                            }}</span>
                        </div>
                    </div>

                    <!-- Numeric keypad -->
                    <!-- eslint-disable-next-line vue/no-mutating-props -->
                    <NumericKeypad v-model="checkout.amount.value" />

                    <!-- Quick bills -->
                    <div class="grid grid-cols-2 content-start gap-2">
                        <button
                            v-for="bill in QUICK_BILLS"
                            :key="bill"
                            type="button"
                            class="rounded-md border border-input px-2 py-2 text-sm font-medium hover:bg-accent"
                            @click="selectBill(bill)"
                        >
                            {{ formatCOP(bill) }}
                        </button>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <Label for="checkout_notes">{{
                        trans('app.fields.notes')
                    }}</Label>
                    <!-- eslint-disable vue/no-mutating-props -->
                    <textarea
                        id="checkout_notes"
                        v-model="checkout.notes.value"
                        rows="2"
                        class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        :placeholder="trans('app.pos.notes_placeholder')"
                    ></textarea>
                    <!-- eslint-enable vue/no-mutating-props -->
                </div>

                <InputError :message="checkout.errors.value.cash_session" />
                <div
                    v-if="Object.keys(checkout.errors.value).length > 0"
                    class="rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    <p class="mb-1 font-medium">
                        {{ trans('app.pos.fix_errors') }}
                    </p>
                    <ul class="list-disc space-y-0.5 pl-5">
                        <li
                            v-for="(message, key) in checkout.errors.value"
                            :key="key"
                        >
                            {{ message }}
                        </li>
                    </ul>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    :disabled="checkout.submitting.value"
                    @click="emit('confirm')"
                >
                    {{
                        checkout.submitting.value
                            ? trans('app.pos.saving')
                            : trans('app.pos.checkout.confirm')
                    }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
