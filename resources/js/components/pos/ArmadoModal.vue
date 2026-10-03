<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import StepCombo from '@/components/pos/StepCombo.vue';
import StepFrame from '@/components/pos/StepFrame.vue';
import StepLens from '@/components/pos/StepLens.vue';
import StepPatient from '@/components/pos/StepPatient.vue';
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
import type { PrescriptionOption } from '@/types';

const { trans } = useTranslations();

const props = defineProps<{
    open: boolean;
    armado: Armado | null;
    lensCatalog: LensCatalogProp;
    kit: KitProp;
    frameProducts: ProductProp[];
    prescriptions: PrescriptionOption[];
    /** The previous armado's prescription, offered again to a new armado. */
    suggestedPrescriptionId?: number | null;
    errors?: Record<string, string>;
    today?: string;
    minExamDate?: string;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    save: [Armado];
    saved: [PrescriptionOption];
}>();

const customerId = defineModel<number | null>('customerId', {
    required: true,
});
const customerLabel = defineModel<string>('customerLabel', { required: true });

const emptyArmado = (): Armado => ({
    id: 0,
    patient_id: null,
    patient_name: '',
    prescription_id: null,
    lens: null,
    frame: null,
    own_frame: false,
    // A slot whose default product is inactive or gone is left out, as the
    // server skips it too.
    slots: props.kit.armado_slots.flatMap((slot) => {
        const product = slot.products.find(
            (p) => p.id === slot.default_product_id,
        );

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

// A deep, proxy-free copy so the draft and the cart never share nested
// objects. structuredClone() throws on the reactive proxies nested in an
// armado (e.g. the lens treatment_ids), and the armado is plain JSON data.
const plainCopy = (armado: Armado): Armado =>
    JSON.parse(JSON.stringify(armado));

const draft = ref<Armado>(emptyArmado());

const patientMode = ref<'payer' | 'other'>('payer');
const otherPatientId = ref<number | null>(null);
const otherPatientLabel = ref('');
const prescriptionMode = ref<'existing' | 'new'>('new');

const patientId = computed(() =>
    patientMode.value === 'payer' ? customerId.value : otherPatientId.value,
);

const patientPrescriptions = computed<PrescriptionOption[]>(() =>
    patientId.value === null
        ? []
        : props.prescriptions.filter((p) => p.customer_id === patientId.value),
);

// A prescription only counts while it belongs to the armado's current
// patient — switching patient invalidates a stale pick instead of carrying
// it over to someone else.
const hasPatientPrescription = computed(() =>
    patientPrescriptions.value.some(
        (p) => p.id === draft.value.prescription_id,
    ),
);

watch(patientPrescriptions, (list) => {
    if (prescriptionMode.value === 'existing' && list.length === 0) {
        prescriptionMode.value = 'new';
    }
});

// The modal works on a local draft so cancelling never touches the cart —
// props seed it fresh each time the dialog opens.
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        step.value = 'prescription';
        draft.value = props.armado ? plainCopy(props.armado) : emptyArmado();

        // An armado whose patient isn't the current payer reopens on "another
        // patient" — including one whose payer changed after it was added.
        const forOther =
            draft.value.patient_id !== null &&
            draft.value.patient_id !== customerId.value;
        patientMode.value = forOther ? 'other' : 'payer';
        otherPatientId.value = forOther ? draft.value.patient_id : null;
        otherPatientLabel.value = forOther ? draft.value.patient_name : '';

        // A new armado starts on the previous armado's prescription (same
        // payer) so a second pair doesn't repeat the pick; the seller can
        // still switch patient or prescription.
        if (
            props.armado === null &&
            props.prescriptions.some(
                (p) =>
                    p.id === props.suggestedPrescriptionId &&
                    p.customer_id === customerId.value,
            )
        ) {
            draft.value.prescription_id = props.suggestedPrescriptionId ?? null;
        }

        prescriptionMode.value =
            draft.value.prescription_id !== null ? 'existing' : 'new';
    },
);

const canSave = computed(
    () => draft.value.lens !== null && hasPatientPrescription.value,
);

// Each step must be fully filled before the wizard lets the user move on —
// otherwise incomplete armados (no lens resolved, no frame chosen, ...)
// silently reach the final "save" button with no earlier warning.
const currentStepValid = computed(() => {
    switch (step.value) {
        case 'prescription':
            return hasPatientPrescription.value;
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
    emit('save', {
        ...plainCopy(draft.value),
        patient_id: patientId.value,
        patient_name:
            patientMode.value === 'payer'
                ? customerLabel.value
                : otherPatientLabel.value,
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ stepTitle }}</DialogTitle>
            </DialogHeader>

            <div v-if="step === 'prescription'" class="flex flex-col gap-4">
                <StepPatient
                    v-model:payer-id="customerId"
                    v-model:payer-label="customerLabel"
                    v-model:mode="patientMode"
                    v-model:patient-id="otherPatientId"
                    v-model:patient-label="otherPatientLabel"
                    :today="today"
                />
                <!-- Keyed by patient so a half-typed new prescription never
                     carries over to a different person. -->
                <StepPrescription
                    v-if="patientId !== null"
                    :key="patientId"
                    v-model:prescription-mode="prescriptionMode"
                    v-model:prescription-id="draft.prescription_id"
                    :customer-id="patientId"
                    :customer-prescriptions="patientPrescriptions"
                    :errors="errors"
                    :today="today"
                    :min-exam-date="minExamDate"
                    @saved="emit('saved', $event)"
                />
            </div>

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
