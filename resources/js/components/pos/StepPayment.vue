<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PaymentMethod {
    id: number;
    name: string;
    surcharge_percent: number;
}

interface DocumentType {
    value: string;
    label: string;
}

interface PaymentErrors {
    document_type?: string;
    'payment.payment_method_id'?: string;
    'payment.amount'?: string;
}

const props = defineProps<{
    paymentMethods: PaymentMethod[];
    documentTypes: DocumentType[];
    total: number;
    formatCOP: (value: number) => string;
    errors?: PaymentErrors;
}>();

const documentType = defineModel<string>('documentType', { required: true });
const showPayment = defineModel<boolean>('showPayment', { required: true });
const paymentMethodId = defineModel<number | null>('paymentMethodId', {
    required: true,
});
const paymentAmount = defineModel<number>('paymentAmount', { required: true });
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Document type -->
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-3 text-base font-semibold">Tipo de documento</h2>
            <div class="flex flex-col gap-2">
                <label
                    v-for="dt in documentTypes"
                    :key="dt.value"
                    class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm transition-colors hover:bg-accent"
                >
                    <input
                        v-model="documentType"
                        type="radio"
                        :value="dt.value"
                        class="size-4 accent-primary"
                    />
                    {{ dt.label }}
                </label>
            </div>
            <InputError class="mt-1" :message="props.errors?.document_type" />
        </div>

        <!-- Payment (abono) -->
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="mb-3 flex items-center gap-2">
                <Checkbox id="show_payment" v-model="showPayment" />
                <Label for="show_payment" class="cursor-pointer font-semibold"
                    >Registrar abono</Label
                >
            </div>

            <div v-if="showPayment" class="flex flex-col gap-3">
                <div>
                    <Label for="payment_method">Método de pago</Label>
                    <select
                        id="payment_method"
                        v-model="paymentMethodId"
                        class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                    >
                        <option :value="null">— Seleccionar —</option>
                        <option
                            v-for="pm in paymentMethods"
                            :key="pm.id"
                            :value="pm.id"
                        >
                            {{ pm.name }}
                        </option>
                    </select>
                    <InputError
                        :message="props.errors?.['payment.payment_method_id']"
                    />
                </div>

                <div>
                    <Label for="payment_amount">Monto del abono</Label>
                    <Input
                        id="payment_amount"
                        v-model.number="paymentAmount"
                        type="number"
                        min="0"
                        :max="total"
                        class="mt-1 w-full text-right"
                        placeholder="0"
                    />
                    <InputError :message="props.errors?.['payment.amount']" />
                    <p
                        v-if="paymentAmount > total"
                        class="mt-1 text-sm text-destructive"
                    >
                        El abono no puede superar el total ({{
                            props.formatCOP(total)
                        }}).
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
