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
    LensProduct,
    LensSpecs,
    ProductProp,
} from '@/composables/useLensCatalog';
import type { Armado } from '@/composables/usePosCart';
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
    lensSelection: LensSpecs | null;
    resolvedLens: LensProduct | null;
    products: ProductProp[];
    frameProducts: ProductProp[];
    recommended: LensSpecs | null;
    warnings: string[];
    customerPrescriptions: PrescriptionOption[];
    lensNeedsCustomer: boolean;
    errors?: Record<string, string>;
    today?: string;
    minExamDate?: string;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    save: [
        {
            armado: Armado;
            lensSelection: LensSpecs;
            resolvedLens: LensProduct | null;
        },
    ];
    'refresh-recommendation': [{ design?: string; material?: string }];
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

const emptySelection = (): LensSpecs => ({
    design: '',
    process: '',
    material: '',
    filter: '',
});

const emptyArmado = (): Armado => ({
    id: 0,
    lens: null,
    frame: null,
    own_frame: false,
    combo: { with_exam: false, estuche: 'small', include_liquid: true, include_pano: true },
});

type WizardStep = 'prescription' | 'lens' | 'frame' | 'combo';
const steps: WizardStep[] = ['prescription', 'lens', 'frame', 'combo'];
const step = ref<WizardStep>('prescription');
const stepIndex = computed(() => steps.indexOf(step.value));
const stepTitle = computed(() => trans(`app.pos.steps.${step.value}`));

const draft = ref<Armado>(emptyArmado());
const draftLensSelection = ref<LensSpecs>(emptySelection());
const draftResolvedLens = ref<LensProduct | null>(null);

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
        draftLensSelection.value = props.lensSelection
            ? structuredClone(props.lensSelection)
            : emptySelection();
        draftResolvedLens.value = props.resolvedLens
            ? structuredClone(props.resolvedLens)
            : null;
    },
);

const canSave = computed(() => draft.value.lens !== null);
const canLeavePrescriptionStep = computed(
    () => !props.lensNeedsCustomer || customerId.value !== null,
);

function goNext(): void {
    if (
        stepIndex.value < steps.length - 1 &&
        (step.value !== 'prescription' || canLeavePrescriptionStep.value)
    ) {
        step.value = steps[stepIndex.value + 1];
    }
}

function goBack(): void {
    if (stepIndex.value > 0) {
        step.value = steps[stepIndex.value - 1];
    }
}

function emitRefresh(): void {
    emit('refresh-recommendation', {
        design: draftLensSelection.value.design || undefined,
        material: draftLensSelection.value.material || undefined,
    });
}

function onLensSelectionChange(): void {
    emitRefresh();

    draft.value.lens = draftResolvedLens.value
        ? {
              product_id: draftResolvedLens.value.id,
              description: draftResolvedLens.value.name,
              unit_price: draftResolvedLens.value.price,
              unit_cost: draftResolvedLens.value.cost,
              option_ids: draftResolvedLens.value.option_ids,
          }
        : null;
}

function save(): void {
    emit('save', {
        armado: draft.value,
        lensSelection: draftLensSelection.value,
        resolvedLens: draftResolvedLens.value,
    });
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
                @change="emitRefresh"
            />

            <StepLens
                v-else-if="step === 'lens'"
                v-model:selection="draftLensSelection"
                v-model:resolved-lens="draftResolvedLens"
                :products="products"
                :recommended="recommended"
                :warnings="warnings"
                @change="onLensSelectionChange"
            />

            <StepFrame
                v-else-if="step === 'frame'"
                v-model:frame="draft.frame"
                v-model:own-frame="draft.own_frame"
                :frame-products="frameProducts"
            />

            <StepCombo v-else v-model:combo="draft.combo" />

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
                    :disabled="
                        step === 'prescription' && !canLeavePrescriptionStep
                    "
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
