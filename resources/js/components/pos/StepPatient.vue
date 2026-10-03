<script setup lang="ts">
import StepCustomer from '@/components/pos/StepCustomer.vue';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

defineProps<{ today?: string }>();

const payerId = defineModel<number | null>('payerId', { required: true });
const payerLabel = defineModel<string>('payerLabel', { required: true });
const mode = defineModel<'payer' | 'other'>('mode', { required: true });
const patientId = defineModel<number | null>('patientId', { required: true });
const patientLabel = defineModel<string>('patientLabel', { required: true });

const toggleClass = (active: boolean): string[] => [
    'rounded-md px-3 py-1.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/50',
    active
        ? 'bg-primary text-primary-foreground'
        : 'border border-input bg-transparent hover:bg-accent',
];
</script>

<template>
    <div>
        <!-- A lens sale requires a paying customer first -->
        <div v-if="payerId === null">
            <div
                class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
            >
                {{ trans('app.pos.prescription_form.needs_customer') }}
            </div>
            <StepCustomer
                v-model:customer-id="payerId"
                v-model:selected-label="payerLabel"
                :today="today"
                :optional="false"
            />
        </div>

        <fieldset v-else class="flex flex-col gap-3">
            <legend class="mb-2 text-sm font-medium">
                {{ trans('app.pos.patient_form.for_whom') }}
            </legend>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    :aria-pressed="mode === 'payer'"
                    :class="toggleClass(mode === 'payer')"
                    @click="mode = 'payer'"
                >
                    {{
                        trans('app.pos.patient_form.payer').replace(
                            ':name',
                            payerLabel,
                        )
                    }}
                </button>
                <button
                    type="button"
                    :aria-pressed="mode === 'other'"
                    :class="toggleClass(mode === 'other')"
                    @click="mode = 'other'"
                >
                    {{ trans('app.pos.patient_form.other') }}
                </button>
            </div>
            <StepCustomer
                v-if="mode === 'other'"
                v-model:customer-id="patientId"
                v-model:selected-label="patientLabel"
                input-id="patient_id"
                :label="trans('app.pos.patient_form.select_patient')"
                :today="today"
                :optional="false"
            />
        </fieldset>
    </div>
</template>
