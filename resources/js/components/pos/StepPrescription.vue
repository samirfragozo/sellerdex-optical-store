<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import DiopterInput from '@/components/DiopterInput.vue';
import InputError from '@/components/InputError.vue';
import StepCustomer from '@/components/pos/StepCustomer.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCreatePrescription } from '@/composables/useCreatePrescription';
import { useTranslations } from '@/composables/useTranslations';
import type { PrescriptionOption } from '@/types';

const { trans } = useTranslations();

interface EyeFields {
    sphere: string;
    cylinder: string;
    axis: string;
    add: string;
    prism: string;
    prism_base: string;
    pd: string;
    va: string;
}

interface PrescriptionForm {
    exam_date: string;
    prescriber_name: string;
    prescriber_license: string;
    notes: string;
    attachment: File | null;
    od: EyeFields;
    os: EyeFields;
}

const eyes = ['od', 'os'] as const;

const props = defineProps<{
    customerPrescriptions: PrescriptionOption[];
    lensNeedsCustomer?: boolean;
    errors?: { prescription_id?: string };
    today?: string;
    minExamDate?: string;
}>();

const emit = defineEmits<{
    saved: [PrescriptionOption];
}>();

const prescriptionMode = defineModel<'existing' | 'new'>('prescriptionMode', {
    required: true,
});
const prescriptionId = defineModel<number | null>('prescriptionId', {
    required: true,
});
const customerId = defineModel<number | null>('customerId', {
    required: true,
});

function emptyEye(): EyeFields {
    return {
        sphere: '',
        cylinder: '',
        axis: '',
        add: '',
        prism: '',
        prism_base: '',
        pd: '',
        va: '',
    };
}

function emptyForm(): PrescriptionForm {
    return {
        exam_date: props.today ?? '',
        prescriber_name: '',
        prescriber_license: '',
        notes: '',
        attachment: null,
        od: emptyEye(),
        os: emptyEye(),
    };
}

const form = ref<PrescriptionForm>(emptyForm());
const attachmentName = ref('');
const savedNotice = ref(false);
const { errors: saveErrors, submitting, submit } = useCreatePrescription();

const selectedPrescription = computed(
    () =>
        props.customerPrescriptions.find(
            (rx) => rx.id === prescriptionId.value,
        ) ?? null,
);

function selectNewMode(): void {
    prescriptionMode.value = 'new';
    // A stale selection from "existing" mode must not keep the step valid
    // (or show its expired warning) once the seller starts a fresh form.
    prescriptionId.value = null;
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.value.attachment = file;
    attachmentName.value = file?.name ?? '';
}

async function save(): Promise<void> {
    if (customerId.value === null) {
        return;
    }

    savedNotice.value = false;
    const created = await submit({
        customer_id: customerId.value,
        exam_date: form.value.exam_date,
        prescriber_name: form.value.prescriber_name,
        prescriber_license: form.value.prescriber_license,
        notes: form.value.notes,
        attachment: form.value.attachment,
        od_sphere: form.value.od.sphere,
        od_cylinder: form.value.od.cylinder,
        od_axis: form.value.od.axis,
        od_add: form.value.od.add,
        od_prism: form.value.od.prism,
        od_prism_base: form.value.od.prism_base,
        od_pd: form.value.od.pd,
        od_va: form.value.od.va,
        os_sphere: form.value.os.sphere,
        os_cylinder: form.value.os.cylinder,
        os_axis: form.value.os.axis,
        os_add: form.value.os.add,
        os_prism: form.value.os.prism,
        os_prism_base: form.value.os.prism_base,
        os_pd: form.value.os.pd,
        os_va: form.value.os.va,
    });

    if (created === null) {
        return;
    }

    // Select the new prescription and stay on this "new" form (rather than
    // switching to "existing") so the confirmation below stays visible —
    // switching modes here would unmount it in the same render.
    emit('saved', created);
    prescriptionId.value = created.id;
    form.value = emptyForm();
    attachmentName.value = '';
    savedNotice.value = true;
}
</script>

<template>
    <div>
        <p class="mb-4 text-xs text-muted-foreground">
            {{ trans('app.pos.prescription_form.required_notice') }}
        </p>

        <!-- A lens sale requires a customer -->
        <div v-if="props.lensNeedsCustomer">
            <div
                class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
            >
                {{ trans('app.pos.prescription_form.needs_customer') }}
            </div>
            <StepCustomer
                v-model:customer-id="customerId"
                :today="props.today"
                :optional="false"
            />
        </div>

        <template v-else>
            <!-- Mode toggle -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    :disabled="customerPrescriptions.length === 0"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors disabled:opacity-40',
                        prescriptionMode === 'existing'
                            ? 'bg-primary text-primary-foreground'
                            : 'border border-input bg-transparent hover:bg-accent',
                    ]"
                    @click="prescriptionMode = 'existing'"
                >
                    {{ trans('app.pos.prescription_form.use_existing') }}
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        prescriptionMode === 'new'
                            ? 'bg-primary text-primary-foreground'
                            : 'border border-input bg-transparent hover:bg-accent',
                    ]"
                    @click="selectNewMode"
                >
                    {{ trans('app.pos.prescription_form.create_new') }}
                </button>
            </div>

            <!-- Existing prescription select -->
            <div v-if="prescriptionMode === 'existing'">
                <Label for="prescription_id">{{
                    trans('app.pos.prescription_form.select_prescription')
                }}</Label>
                <select
                    id="prescription_id"
                    v-model="prescriptionId"
                    class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                >
                    <option :value="null">
                        {{ trans('app.pos.select_option') }}
                    </option>
                    <option
                        v-for="rx in customerPrescriptions"
                        :key="rx.id"
                        :value="rx.id"
                    >
                        {{ rx.exam_date }} — {{ rx.summary }}
                        ·
                        {{
                            trans(
                                'app.pos.prescription_form.expires_on',
                            ).replace(':date', rx.expires_at ?? '—')
                        }}
                        {{
                            rx.is_expired
                                ? '· ' +
                                  trans(
                                      'app.pos.prescription_form.expired_badge',
                                  )
                                : ''
                        }}
                    </option>
                </select>
                <InputError
                    class="mt-1"
                    :message="props.errors?.prescription_id"
                />
            </div>

            <!-- New prescription inline form -->
            <div v-else class="flex flex-col gap-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label for="rx_exam_date" class="text-xs">{{
                            trans('app.fields.exam_date')
                        }}</Label>
                        <Input
                            id="rx_exam_date"
                            v-model="form.exam_date"
                            type="date"
                            :min="props.minExamDate"
                            :max="props.today"
                            class="mt-1 h-8 w-full text-sm"
                        />
                        <InputError
                            class="mt-1"
                            :message="saveErrors.exam_date"
                        />
                    </div>
                    <div>
                        <Label for="rx_prescriber_name" class="text-xs">{{
                            trans('app.fields.prescriber_name')
                        }}</Label>
                        <Input
                            id="rx_prescriber_name"
                            v-model="form.prescriber_name"
                            class="mt-1 h-8 w-full text-sm"
                        />
                        <InputError
                            class="mt-1"
                            :message="saveErrors.prescriber_name"
                        />
                    </div>
                    <div>
                        <Label for="rx_prescriber_license" class="text-xs">{{
                            trans('app.fields.prescriber_license')
                        }}</Label>
                        <Input
                            id="rx_prescriber_license"
                            v-model="form.prescriber_license"
                            class="mt-1 h-8 w-full text-sm"
                        />
                        <InputError
                            class="mt-1"
                            :message="saveErrors.prescriber_license"
                        />
                    </div>
                </div>

                <!-- Refraction grid -->
                <div class="overflow-x-auto">
                    <div
                        class="grid min-w-[36rem] grid-cols-[1.5rem_1fr_1fr_1fr_1fr_1fr_1fr_1fr_1fr] gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span></span>
                        <span>{{ trans('app.fields.sphere') }}</span>
                        <span>{{ trans('app.fields.cylinder') }}</span>
                        <span>{{ trans('app.fields.axis') }}</span>
                        <span>{{ trans('app.fields.add') }}</span>
                        <span>{{ trans('app.fields.prism') }}</span>
                        <span>{{ trans('app.fields.prism_base') }}</span>
                        <span>{{ trans('app.fields.pd') }}</span>
                        <span>{{ trans('app.fields.va') }}</span>
                    </div>
                    <div
                        v-for="eye in eyes"
                        :key="eye"
                        class="mt-1.5 grid min-w-[36rem] grid-cols-[1.5rem_1fr_1fr_1fr_1fr_1fr_1fr_1fr_1fr] items-start gap-1.5"
                    >
                        <span class="pt-1.5 text-xs font-medium">{{
                            eye.toUpperCase()
                        }}</span>
                        <div>
                            <DiopterInput
                                v-model="form[eye].sphere"
                                :min="0"
                                :max="20"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.sphere')}`"
                                :aria-describedby="`rx_${eye}_sphere_error`"
                            />
                            <InputError
                                :id="`rx_${eye}_sphere_error`"
                                :message="saveErrors[`${eye}_sphere`]"
                            />
                        </div>
                        <div>
                            <DiopterInput
                                v-model="form[eye].cylinder"
                                :min="0"
                                :max="10"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.cylinder')}`"
                                :aria-describedby="`rx_${eye}_cylinder_error`"
                            />
                            <InputError
                                :id="`rx_${eye}_cylinder_error`"
                                :message="saveErrors[`${eye}_cylinder`]"
                            />
                        </div>
                        <div>
                            <Input
                                v-model="form[eye].axis"
                                type="number"
                                min="1"
                                max="180"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.axis')}`"
                                :aria-describedby="`rx_${eye}_axis_error`"
                                class="h-8 w-full px-1.5 text-right text-xs"
                            />
                            <InputError
                                :id="`rx_${eye}_axis_error`"
                                :message="saveErrors[`${eye}_axis`]"
                            />
                        </div>
                        <div>
                            <DiopterInput
                                v-model="form[eye].add"
                                fixed-sign="+"
                                :min="0"
                                :max="4"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.add')}`"
                                :aria-describedby="`rx_${eye}_add_error`"
                            />
                            <InputError
                                :id="`rx_${eye}_add_error`"
                                :message="saveErrors[`${eye}_add`]"
                            />
                        </div>
                        <div>
                            <Input
                                v-model="form[eye].prism"
                                type="number"
                                min="0"
                                max="10"
                                step="0.25"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.prism')}`"
                                :aria-describedby="`rx_${eye}_prism_error`"
                                class="h-8 w-full px-1.5 text-right text-xs"
                            />
                            <InputError
                                :id="`rx_${eye}_prism_error`"
                                :message="saveErrors[`${eye}_prism`]"
                            />
                        </div>
                        <div>
                            <select
                                v-model="form[eye].prism_base"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.prism_base')}`"
                                :aria-describedby="`rx_${eye}_prism_base_error`"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-1 text-xs outline-none dark:bg-input/30"
                            >
                                <option value="">—</option>
                                <option value="up">
                                    {{ trans('app.prism_base.up') }}
                                </option>
                                <option value="down">
                                    {{ trans('app.prism_base.down') }}
                                </option>
                                <option value="in">
                                    {{ trans('app.prism_base.in') }}
                                </option>
                                <option value="out">
                                    {{ trans('app.prism_base.out') }}
                                </option>
                            </select>
                            <InputError
                                :id="`rx_${eye}_prism_base_error`"
                                :message="saveErrors[`${eye}_prism_base`]"
                            />
                        </div>
                        <div>
                            <Input
                                v-model="form[eye].pd"
                                type="number"
                                min="20"
                                max="40"
                                step="0.5"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.pd')}`"
                                :aria-describedby="`rx_${eye}_pd_error`"
                                class="h-8 w-full px-1.5 text-right text-xs"
                            />
                            <InputError
                                :id="`rx_${eye}_pd_error`"
                                :message="saveErrors[`${eye}_pd`]"
                            />
                        </div>
                        <div>
                            <Input
                                v-model="form[eye].va"
                                :aria-label="`${eye.toUpperCase()} — ${trans('app.fields.va')}`"
                                :aria-describedby="`rx_${eye}_va_error`"
                                class="h-8 w-full px-1.5 text-right text-xs"
                            />
                            <InputError
                                :id="`rx_${eye}_va_error`"
                                :message="saveErrors[`${eye}_va`]"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <Label for="rx_notes">{{
                        trans('app.fields.notes')
                    }}</Label>
                    <textarea
                        id="rx_notes"
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        :placeholder="
                            trans('app.pos.prescription_form.notes_placeholder')
                        "
                    ></textarea>
                    <InputError class="mt-1" :message="saveErrors.notes" />
                </div>

                <div>
                    <Label for="rx_attachment">{{
                        trans('app.fields.attachment')
                    }}</Label>
                    <input
                        id="rx_attachment"
                        type="file"
                        accept="image/*,application/pdf"
                        class="mt-1 block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        @change="onFileChange"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ trans('app.pos.prescription_form.attachment_hint') }}
                    </p>
                    <InputError class="mt-1" :message="saveErrors.attachment" />
                </div>

                <div class="flex items-center gap-3">
                    <Button
                        type="button"
                        size="sm"
                        :disabled="submitting"
                        @click="save"
                    >
                        {{ trans('app.pos.prescription_form.save') }}
                    </Button>
                    <span
                        v-if="savedNotice"
                        class="text-sm text-muted-foreground"
                    >
                        {{ trans('app.pos.prescription_form.saved') }}
                    </span>
                </div>
                <InputError :message="saveErrors.customer_id" />
                <InputError :message="saveErrors.general" />
            </div>
        </template>

        <Alert
            v-if="selectedPrescription?.is_expired"
            role="status"
            class="mt-3"
        >
            <TriangleAlert class="size-4" aria-hidden="true" />
            <AlertDescription>
                {{ trans('app.pos.prescription_form.expired_warning') }}
            </AlertDescription>
        </Alert>

        <InputError
            v-if="prescriptionMode === 'new'"
            class="mt-2"
            :message="props.errors?.prescription_id"
        />
    </div>
</template>
