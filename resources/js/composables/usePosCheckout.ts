import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { csrfFetch } from '@/lib/csrfFetch';
import { store } from '@/routes/pos';
import type { CreatedSale } from '@/types/global';

export interface PaymentEntry {
    payment_method_id: number | null;
    amount: number;
}

export interface CheckoutBasePayload {
    customer_id: number | null;
    customer: Record<string, unknown> | null;
    prescription_id: number | null;
    prescription: Record<string, unknown> | null;
    armados: unknown[];
    products: unknown[];
    discount_percent: number;
    tip_percent: number;
    surcharge_percent: number;
}

export function usePosCheckout(total: Ref<number>) {
    const documentType = ref('order');
    const notes = ref('');
    const payments = ref<PaymentEntry[]>([]);
    const amount = ref(0);
    const errors: Ref<Record<string, string>> = ref({});
    const submitting = ref(false);

    function addPayment(paymentMethodId: number, paymentAmount: number): void {
        payments.value.push({
            payment_method_id: paymentMethodId,
            amount: paymentAmount,
        });
    }

    function removePayment(index: number): void {
        payments.value.splice(index, 1);
    }

    const totalPayments: ComputedRef<number> = computed(() =>
        payments.value.reduce((sum, p) => sum + (p.amount || 0), 0),
    );

    const remaining: ComputedRef<number> = computed(
        () => total.value - totalPayments.value,
    );

    // The amount tendered beyond what's owed — meaningful once the last
    // payment (typically cash) overshoots the remaining balance.
    const change: ComputedRef<number> = computed(() =>
        Math.max(0, -remaining.value),
    );

    async function submit(
        payload: CheckoutBasePayload,
    ): Promise<CreatedSale | null> {
        submitting.value = true;
        errors.value = {};

        try {
            const response = await csrfFetch(store.url(), {
                method: 'POST',
                body: JSON.stringify({
                    ...payload,
                    document_type: documentType.value,
                    notes: notes.value,
                    payments: payments.value.filter((p) => p.amount > 0),
                }),
            });

            if (response.status === 422) {
                const body = (await response.json()) as {
                    errors: Record<string, string[]>;
                };
                errors.value = Object.fromEntries(
                    Object.entries(body.errors).map(([key, messages]) => [
                        key,
                        messages[0],
                    ]),
                );

                return null;
            }

            if (response.status === 403) {
                const body = (await response.json()) as { message: string };
                errors.value = { cash_session: body.message };

                return null;
            }

            if (!response.ok) {
                throw new Error(
                    `Unexpected POS checkout response: ${response.status}`,
                );
            }

            return (await response.json()) as CreatedSale;
        } finally {
            submitting.value = false;
        }
    }

    function reset(): void {
        documentType.value = 'order';
        notes.value = '';
        payments.value = [];
        amount.value = 0;
        errors.value = {};
    }

    return {
        documentType,
        notes,
        payments,
        amount,
        errors,
        submitting,
        addPayment,
        removePayment,
        totalPayments,
        remaining,
        change,
        submit,
        reset,
    };
}
