<script setup lang="ts">
import DiopterInput from '@/components/DiopterInput.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

interface PrescriptionErrors {
    prescription_id?: string;
    prescription?: string;
    'prescription.exam_date'?: string;
}

const props = defineProps<{
    customerPrescriptions: PrescriptionOption[];
    lensNeedsCustomer?: boolean;
    errors?: PrescriptionErrors;
    today?: string;
    minExamDate?: string;
}>();

const emit = defineEmits<{
    change: [];
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

function onRefractionChange(): void {
    emit('change');
}
</script>

<template>
    <div>
        <p class="mb-4 text-xs text-muted-foreground">
            Obligatoria para vender lentes formulados.
        </p>

        <!-- A lens sale requires a customer -->
        <div
            v-if="props.lensNeedsCustomer"
            class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
        >
            Para vender lentes formulados debes seleccionar o registrar un
            cliente.
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
                    Usar existente
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        prescriptionMode === 'new'
                            ? 'bg-primary text-primary-foreground'
                            : 'border border-input bg-transparent hover:bg-accent',
                    ]"
                    @click="prescriptionMode = 'new'"
                >
                    Crear nueva
                </button>
            </div>

            <!-- Existing prescription select -->
            <div v-if="prescriptionMode === 'existing'">
                <Label for="prescription_id">Seleccionar prescripción</Label>
                <select
                    id="prescription_id"
                    v-model="prescriptionId"
                    class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                >
                    <option :value="null">— Seleccionar —</option>
                    <option
                        v-for="rx in customerPrescriptions"
                        :key="rx.id"
                        :value="rx.id"
                    >
                        {{ rx.exam_date }} — {{ rx.summary }}
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
                        <Label for="rx_exam_date">Fecha del examen</Label>
                        <Input
                            id="rx_exam_date"
                            v-model="prescription.exam_date"
                            type="date"
                            :min="props.minExamDate"
                            :max="props.today"
                            class="mt-1 w-full"
                        />
                        <InputError
                            class="mt-1"
                            :message="props.errors?.['prescription.exam_date']"
                        />
                    </div>
                </div>

                <!-- Refraction grid -->
                <div class="overflow-x-auto">
                    <div
                        class="grid min-w-[26rem] grid-cols-[2rem_1fr_1fr_1fr_1fr] gap-2 text-xs font-medium text-muted-foreground"
                    >
                        <span></span>
                        <span>Esfera</span>
                        <span>Cilindro</span>
                        <span>Eje</span>
                        <span>Add</span>
                    </div>
                    <div
                        class="mt-1 grid min-w-[26rem] grid-cols-[2rem_1fr_1fr_1fr_1fr] items-center gap-2"
                    >
                        <span class="text-sm font-medium">OD</span>
                        <DiopterInput
                            v-model="prescription.od_sphere"
                            :min="0"
                            :max="20"
                            @update:model-value="onRefractionChange"
                        />
                        <DiopterInput
                            v-model="prescription.od_cylinder"
                            :min="0"
                            :max="10"
                            @update:model-value="onRefractionChange"
                        />
                        <Input
                            v-model="prescription.od_axis"
                            type="number"
                            min="1"
                            max="180"
                            class="w-full text-right"
                            @update:model-value="onRefractionChange"
                        />
                        <DiopterInput
                            v-model="prescription.od_add"
                            fixed-sign="+"
                            :min="0"
                            :max="4"
                            @update:model-value="onRefractionChange"
                        />
                    </div>
                    <div
                        class="mt-2 grid min-w-[26rem] grid-cols-[2rem_1fr_1fr_1fr_1fr] items-center gap-2"
                    >
                        <span class="text-sm font-medium">OS</span>
                        <DiopterInput
                            v-model="prescription.os_sphere"
                            :min="0"
                            :max="20"
                            @update:model-value="onRefractionChange"
                        />
                        <DiopterInput
                            v-model="prescription.os_cylinder"
                            :min="0"
                            :max="10"
                            @update:model-value="onRefractionChange"
                        />
                        <Input
                            v-model="prescription.os_axis"
                            type="number"
                            min="1"
                            max="180"
                            class="w-full text-right"
                            @update:model-value="onRefractionChange"
                        />
                        <DiopterInput
                            v-model="prescription.os_add"
                            fixed-sign="+"
                            :min="0"
                            :max="4"
                            @update:model-value="onRefractionChange"
                        />
                    </div>
                </div>

                <div>
                    <Label for="rx_diagnosis">Diagnóstico</Label>
                    <textarea
                        id="rx_diagnosis"
                        v-model="prescription.diagnosis"
                        rows="2"
                        class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        placeholder="Opcional..."
                    ></textarea>
                </div>
            </div>
        </template>

        <InputError class="mt-2" :message="props.errors?.prescription" />
    </div>
</template>
