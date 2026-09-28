<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import StepCombo from '@/components/pos/StepCombo.vue';
import StepFrame from '@/components/pos/StepFrame.vue';
import StepLens from '@/components/pos/StepLens.vue';
import StepPrescription from '@/components/pos/StepPrescription.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import type {
    LensCatalogProp,
    ProductProp,
} from '@/composables/useLensCatalog';
import type { Armado, KitProp } from '@/composables/usePosCart';
import { useTranslations } from '@/composables/useTranslations';

interface PrescriptionOption {
    id: number;
    customer_id: number;
    exam_date: string | null;
    lens_type: string | null;
    summary: string;
}

interface NewPrescription {
    exam_date: string;
    lens_type: string;
    od_sphere: string;
    od_cylinder: string;
    od_axis: string;
    od_add: string;
    os_sphere: string;
    os_cylinder: string;
    os_axis: string;
    os_add: string;
    diagnosis: string;
}

const { trans } = useTranslations();

const props = defineProps<{
    open: boolean;
    armado: Armado | null;
    lensCatalog: LensCatalogProp;
    kit: KitProp;
    frameProducts: ProductProp[];
    customerPrescriptions: PrescriptionOption[];
    lensNeedsCustomer: boolean;
    errors?: Record<string, string>;
    today?: string;
    minExamDate?: string;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    save: [Armado];
}>();

const prescriptionMode = defineModel<'existing' | 'new'>('prescriptionMode', {
    required: true,
});
const prescriptionId = defineModel<number | null>('prescriptionId', {
    required: true,
});
const prescription = defineModel<NewPrescription>('prescription', {
    required: true,
});
const customerId = defineModel<number | null>('customerId', {
    required: true,
});

const emptyArmado = (): Armado => ({
    id: 0,
    lens: null,
    frame: null,
    own_frame: false,
    // Slots without an active product are left out; the server skips them too.
    slots: props.kit.armado_slots.flatMap((slot) => {
        const product =
            slot.products.find((p) => p.id === slot.default_product_id) ??
            slot.products[0];

        return product
            ? [
                  {
                      kit_slot_id: slot.id,
                      product_id: product.id,
                      selected: slot.is_optional ? slot.is_preselected : true,
                  },
              ]
            : [];
    }),
});

type WizardStep = 'prescription' | 'lens' | 'frame' | 'combo';
const steps: WizardStep[] = ['prescription', 'lens', 'frame', 'combo'];
const step = ref<WizardStep>('prescription');
const stepIndex = computed(() => steps.indexOf(step.value));
const stepTitle = computed(() => trans(`app.pos.steps.${step.value}`));

const draft = ref<Armado>(emptyArmado());
// The modal works on a local draft so cancelling never touches the cart —
// props seed it fresh each time the dialog opens.
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        step.value = 'prescription';
        draft.value = props.armado
            ? structuredClone(props.armado)
            : emptyArmado();
    },
);

const canSave = computed(() => draft.value.lens !== null);

// Each step must be fully filled before the wizard lets the user move on —
// otherwise incomplete armados (no lens resolved, no frame chosen, ...)
// silently reach the final "save" button with no earlier warning.
const currentStepValid = computed(() => {
    switch (step.value) {
        case 'prescription':
            if (props.lensNeedsCustomer) {
                return customerId.value !== null;
            }

            return prescriptionMode.value === 'existing'
                ? prescriptionId.value !== null
                : prescription.value.exam_date !== '' &&
                      prescription.value.lens_type !== '';
        case 'lens':
            return draft.value.lens !== null;
        case 'frame':
            return draft.value.own_frame || draft.value.frame !== null;
        default:
            return true;
    }
});

function goNext(): void {
    if (stepIndex.value < steps.length - 1 && currentStepValid.value) {
        step.value = steps[stepIndex.value + 1];
    }
}

function goBack(): void {
    if (stepIndex.value > 0) {
        step.value = steps[stepIndex.value - 1];
    }
}

function save(): void {
    emit('save', draft.value);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ stepTitle }}</DialogTitle>
            </DialogHeader>

            <StepPrescription
                v-if="step === 'prescription'"
                v-model:prescription-mode="prescriptionMode"
                v-model:prescription-id="prescriptionId"
                v-model:prescription="prescription"
                v-model:customer-id="customerId"
                :customer-prescriptions="customerPrescriptions"
                :lens-needs-customer="lensNeedsCustomer"
                :errors="errors"
                :today="today"
                :min-exam-date="minExamDate"
            />

            <StepLens
                v-else-if="step === 'lens'"
                :catalog="lensCatalog"
                :initial="draft.lens"
                @change="draft.lens = $event"
            />

            <StepFrame
                v-else-if="step === 'frame'"
                v-model:frame="draft.frame"
                v-model:own-frame="draft.own_frame"
                :frame-products="frameProducts"
            />

            <StepCombo
                v-else
                v-model:slots="draft.slots"
                :kit-slots="kit.armado_slots"
            />

            <DialogFooter class="sm:justify-between">
                <Button
                    v-if="stepIndex > 0"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="goBack"
                >
                    {{ trans('app.pos.back') }}
                </Button>
                <span v-else />

                <Button
                    v-if="stepIndex < steps.length - 1"
                    type="button"
                    size="sm"
                    :disabled="!currentStepValid"
                    @click="goNext"
                >
                    {{ trans(`app.pos.continue_to_${steps[stepIndex + 1]}`) }}
                </Button>
                <Button
                    v-else
                    type="button"
                    size="sm"
                    :disabled="!canSave"
                    @click="save"
                >
                    {{ trans('app.pos.save_armado') }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
