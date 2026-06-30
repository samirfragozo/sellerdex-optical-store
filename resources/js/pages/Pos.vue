<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Download,
    Eye,
    FileText,
    Plus,
    ShoppingCart,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import AccordionStep from '@/components/pos/AccordionStep.vue';
import CartSummary from '@/components/pos/CartSummary.vue';
import StepCombo from '@/components/pos/StepCombo.vue';
import StepCustomer from '@/components/pos/StepCustomer.vue';
import StepFrame from '@/components/pos/StepFrame.vue';
import StepLens from '@/components/pos/StepLens.vue';
import StepPayment from '@/components/pos/StepPayment.vue';
import StepPrescription from '@/components/pos/StepPrescription.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    LensProduct,
    LensSpecs,
    ProductProp,
} from '@/composables/useLensCatalog';
import { useLensRecommendation } from '@/composables/useLensRecommendation';
import type { Armado } from '@/composables/usePosCart';
import { usePosCart } from '@/composables/usePosCart';
import { index, store } from '@/routes/pos';
import type { CreatedSale } from '@/types/global';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Nueva venta',
                href: index(),
            },
        ],
    },
});

interface PaymentMethod {
    id: number;
    name: string;
    surcharge_percent: number;
}

interface Customer {
    id: number;
    name: string;
    last_name: string;
    id_number: string | null;
}

interface PrescriptionOption {
    id: number;
    customer_id: number;
    exam_date: string | null;
    lens_type: string | null;
    summary: string;
}

const props = defineProps<{
    products: ProductProp[];
    paymentMethods: PaymentMethod[];
    customers: Customer[];
    prescriptions: PrescriptionOption[];
    lensTypes: Record<string, string>;
}>();

const today = new Date().toISOString().slice(0, 10);
const minExamDate = (() => {
    const d = new Date();
    d.setFullYear(d.getFullYear() - 2);

    return d.toISOString().slice(0, 10);
})();

const page = usePage();
const createdSale = computed<CreatedSale | null>(
    () =>
        (page.props.flash as { createdSale?: CreatedSale | null })
            ?.createdSale ?? null,
);

// --- Cart + recommendation composables ---
const cart = usePosCart();
const { recommended, warnings, fetchFor } = useLensRecommendation();

// Per-armado lens selection + resolvedLens (keyed by armado.id)
const lensSelections = ref<Record<number, LensSpecs>>({});
const resolvedLenses = ref<Record<number, LensProduct | null>>({});

function getLensSelection(armadoId: number): LensSpecs {
    if (!lensSelections.value[armadoId]) {
        lensSelections.value[armadoId] = {
            design: '',
            process: '',
            material: '',
            filter: '',
        };
    }

    return lensSelections.value[armadoId];
}

// --- Accordion state ---
type StepKey =
    | 'customer'
    | `prescription-${number}`
    | `lens-${number}`
    | `frame-${number}`
    | `combo-${number}`
    | 'payment';

const openStep = ref<StepKey>('customer');

function toggle(step: StepKey): void {
    openStep.value = openStep.value === step ? ('customer' as StepKey) : step;
}

// --- Form state ---
const form = useForm({
    customer_id: null as number | null,
    customer: {
        name: '',
        last_name: '',
        document_type: 'cc',
        id_number: '',
        phone: '',
        address: '',
        city: '',
        birth_date: '',
        email: '',
        notes: '',
    },
    document_type: 'order',
    prescription_id: null as number | null,
    prescription: {
        exam_date: today,
        lens_type: '',
        od_sphere: '',
        od_cylinder: '',
        od_axis: '',
        od_add: '',
        os_sphere: '',
        os_cylinder: '',
        os_axis: '',
        os_add: '',
        diagnosis: '',
    },
    notes: '',
});

// --- Customer mode ---
const customerMode = ref<'none' | 'existing' | 'new'>('existing');

watch(customerMode, (mode) => {
    if (mode === 'new') {
        form.customer_id = null;
    } else {
        form.customer = {
            name: '',
            last_name: '',
            document_type: 'cc',
            id_number: '',
            phone: '',
            address: '',
            city: '',
            birth_date: '',
            email: '',
            notes: '',
        };
    }

    if (mode !== 'existing') {
        form.customer_id = null;
    }
});

// Customer summary for accordion collapsed view
const customerSummary = computed(() => {
    if (customerMode.value === 'none') {
        return 'Sin cliente';
    }

    if (customerMode.value === 'existing' && form.customer_id !== null) {
        const c = props.customers.find((c) => c.id === form.customer_id);

        return c ? `${c.name} ${c.last_name}` : 'Cliente seleccionado';
    }

    if (customerMode.value === 'new' && form.customer.name) {
        return `${form.customer.name} ${form.customer.last_name}`.trim();
    }

    return undefined;
});

// --- Prescription mode ---
const prescriptionMode = ref<'existing' | 'new'>('new');

const customerPrescriptions = computed<PrescriptionOption[]>(() =>
    form.customer_id === null
        ? []
        : props.prescriptions.filter((p) => p.customer_id === form.customer_id),
);

const lensNeedsCustomer = computed(
    () => cart.armados.value.length > 0 && customerMode.value === 'none',
);

watch([customerMode, customerPrescriptions], () => {
    if (
        prescriptionMode.value === 'existing' &&
        customerPrescriptions.value.length === 0
    ) {
        prescriptionMode.value = 'new';
    }
});

// --- Payment ---
const showPayment = ref(false);
const paymentMethodId = ref<number | null>(null);
const paymentAmount = ref(0);

watch([paymentMethodId, paymentAmount], () => {
    if (paymentMethodId.value !== null) {
        const pm = props.paymentMethods.find(
            (p) => p.id === paymentMethodId.value,
        );
        cart.surchargePercent.value = pm?.surcharge_percent ?? 0;
    } else {
        cart.surchargePercent.value = 0;
    }
});

const payment = computed(() =>
    showPayment.value
        ? {
              payment_method_id: paymentMethodId.value,
              amount: paymentAmount.value,
          }
        : null,
);

const balance = computed(() => {
    if (!showPayment.value || !payment.value) {
        return cart.total.value;
    }

    return cart.total.value - (payment.value.amount || 0);
});

// --- Document types ---
const documentTypes = [
    { value: 'quote', label: 'Cotización' },
    { value: 'order', label: 'Venta' },
    { value: 'layaway', label: 'Plan separe' },
];

// --- Frame products ---
const frameProducts = computed<ProductProp[]>(() =>
    props.products.filter((p) => p.category_key === 'frame'),
);

// --- Armado management ---
function startArmado(): void {
    const armado = cart.addArmado();
    lensSelections.value[armado.id] = {
        design: '',
        process: '',
        material: '',
        filter: '',
    };
    resolvedLenses.value[armado.id] = null;
    openStep.value = `prescription-${armado.id}`;
}

function removeArmado(id: number): void {
    cart.removeArmado(id);
    delete lensSelections.value[id];
    delete resolvedLenses.value[id];

    if (cart.armados.value.length === 0) {
        openStep.value = 'customer';
    }
}

// --- Loose product management ---
function addLooseProduct(): void {
    cart.addProduct();
}

function removeLooseProduct(index: number): void {
    cart.removeProduct(index);
}

function onLooseProductSelect(index: number, productId: string): void {
    const id = productId ? parseInt(productId, 10) : null;
    const item = cart.products.value[index];

    if (!item) {
        return;
    }

    item.product_id = id;

    if (id !== null) {
        const product = props.products.find((p) => p.id === id);

        if (product) {
            item.description = product.name;
            item.unit_price = product.price;
        }
    }
}

// --- Lens selection change → refresh recommendation ---
function onLensSelectionChange(armado: Armado, sel: LensSpecs): void {
    void fetchFor(form.prescription as Record<string, unknown>, {
        design: sel.design || undefined,
        material: sel.material || undefined,
    });
    // Sync resolved lens back to armado
    const resolved = resolvedLenses.value[armado.id];

    if (resolved) {
        armado.lens = {
            product_id: resolved.id,
            description: resolved.name,
            unit_price: resolved.price,
        };
    } else {
        armado.lens = null;
    }
}

// Prescription change → refresh recommendation for first armado
function onPrescriptionChange(): void {
    const firstArmado = cart.armados.value[0];

    if (!firstArmado) {
        return;
    }

    const sel = getLensSelection(firstArmado.id);
    void fetchFor(form.prescription as Record<string, unknown>, {
        design: sel.design || undefined,
        material: sel.material || undefined,
    });
}

// --- Formatting ---
function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

// --- Submit ---
function submit(): void {
    const cartPayload = cart.buildPayload();
    form.transform((data) => ({
        ...data,
        customer: customerMode.value === 'new' ? data.customer : null,
        prescription_id:
            cart.armados.value.length > 0 &&
            prescriptionMode.value === 'existing'
                ? data.prescription_id
                : null,
        prescription:
            cart.armados.value.length > 0 && prescriptionMode.value === 'new'
                ? data.prescription
                : null,
        armados: cartPayload.armados,
        products: cartPayload.products,
        discount: cart.discount.value,
        surcharge_percent: cart.surchargePercent.value,
        payment: payment.value,
    })).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            cart.armados.value = [];
            cart.products.value = [];
            cart.discount.value = 0;
            cart.surchargePercent.value = 0;
            lensSelections.value = {};
            resolvedLenses.value = {};
            customerMode.value = 'existing';
            prescriptionMode.value = 'new';
            showPayment.value = false;
            paymentMethodId.value = null;
            paymentAmount.value = 0;
            openStep.value = 'customer';
        },
    });
}
</script>

<template>
    <Head title="Nueva venta" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <div class="flex items-center gap-3">
            <ShoppingCart class="size-6 text-muted-foreground" />
            <h1 class="text-2xl font-semibold">Nueva venta</h1>
        </div>

        <!-- Print / download panel shown after a successful sale -->
        <div
            v-if="createdSale"
            class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/30"
        >
            <p
                class="mb-3 text-base font-semibold text-green-800 dark:text-green-300"
            >
                Venta {{ createdSale.number }} creada exitosamente
            </p>
            <div class="flex flex-wrap gap-2">
                <a
                    :href="createdSale.invoice_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <Eye class="size-4" />
                    Imprimir factura
                </a>
                <a
                    :href="createdSale.invoice_pdf_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <Download class="size-4" />
                    Descargar PDF
                </a>
                <a
                    v-if="createdSale.formula_url"
                    :href="createdSale.formula_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <FileText class="size-4" />
                    Imprimir fórmula
                </a>
            </div>
        </div>

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
            <!-- Left column: accordion steps -->
            <div class="flex flex-col gap-3 lg:col-span-2">
                <!-- Step: Cliente -->
                <AccordionStep
                    title="Cliente"
                    :open="openStep === 'customer'"
                    :summary="customerSummary"
                    @toggle="toggle('customer')"
                >
                    <StepCustomer
                        v-model:customer-mode="customerMode"
                        v-model:customer-id="form.customer_id"
                        v-model:customer="form.customer"
                        :customers="customers"
                        :errors="form.errors"
                        :today="today"
                    />
                </AccordionStep>

                <!-- Per-armado steps -->
                <template v-for="armado in cart.armados.value" :key="armado.id">
                    <!-- Prescripción -->
                    <AccordionStep
                        :title="`Prescripción (Armado #${armado.id})`"
                        :open="openStep === `prescription-${armado.id}`"
                        @toggle="toggle(`prescription-${armado.id}`)"
                    >
                        <StepPrescription
                            v-model:prescription-mode="prescriptionMode"
                            v-model:prescription-id="form.prescription_id"
                            v-model:prescription="form.prescription"
                            :customer-prescriptions="customerPrescriptions"
                            :lens-types="lensTypes"
                            :lens-needs-customer="lensNeedsCustomer"
                            :errors="form.errors"
                            :today="today"
                            :min-exam-date="minExamDate"
                            @change="onPrescriptionChange"
                        />
                        <div class="mt-4 flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="toggle(`lens-${armado.id}`)"
                            >
                                Continuar al lente
                            </Button>
                        </div>
                    </AccordionStep>

                    <!-- Lente -->
                    <AccordionStep
                        :title="`Lente (Armado #${armado.id})`"
                        :open="openStep === `lens-${armado.id}`"
                        @toggle="toggle(`lens-${armado.id}`)"
                    >
                        <StepLens
                            v-model:selection="lensSelections[armado.id]"
                            v-model:resolved-lens="resolvedLenses[armado.id]"
                            :products="products"
                            :recommended="recommended"
                            :warnings="warnings"
                            @change="
                                (sel) => onLensSelectionChange(armado, sel)
                            "
                        />
                        <div class="mt-4 flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="toggle(`frame-${armado.id}`)"
                            >
                                Continuar a la montura
                            </Button>
                        </div>
                    </AccordionStep>

                    <!-- Montura -->
                    <AccordionStep
                        :title="`Montura (Armado #${armado.id})`"
                        :open="openStep === `frame-${armado.id}`"
                        @toggle="toggle(`frame-${armado.id}`)"
                    >
                        <StepFrame
                            v-model:frame="armado.frame"
                            v-model:own-frame="armado.own_frame"
                            :frame-products="frameProducts"
                        />
                        <div class="mt-4 flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="toggle(`combo-${armado.id}`)"
                            >
                                Continuar al combo
                            </Button>
                        </div>
                    </AccordionStep>

                    <!-- Combo -->
                    <AccordionStep
                        :title="`Combo (Armado #${armado.id})`"
                        :open="openStep === `combo-${armado.id}`"
                        @toggle="toggle(`combo-${armado.id}`)"
                    >
                        <StepCombo v-model:combo="armado.combo" />
                        <div class="mt-4 flex items-center justify-between">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="removeArmado(armado.id)"
                            >
                                <Trash2 class="mr-1.5 size-4" />
                                Quitar este armado
                            </Button>
                        </div>
                    </AccordionStep>
                </template>

                <!-- Loose products -->
                <template v-if="cart.products.value.length > 0">
                    <div
                        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <h2 class="mb-3 text-base font-semibold">
                            Productos adicionales
                        </h2>
                        <div class="flex flex-col gap-3">
                            <!-- Header -->
                            <div
                                class="hidden grid-cols-[1fr_2fr_5rem_6rem_2rem] gap-2 text-xs font-medium text-muted-foreground sm:grid"
                            >
                                <span>Producto</span>
                                <span>Descripción</span>
                                <span class="text-right">Cant.</span>
                                <span class="text-right">P. unitario</span>
                                <span></span>
                            </div>

                            <div
                                v-for="(item, idx) in cart.products.value"
                                :key="idx"
                                class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_2fr_5rem_6rem_2rem] sm:items-center"
                            >
                                <select
                                    :value="item.product_id ?? ''"
                                    class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                                    @change="
                                        onLooseProductSelect(
                                            idx,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="">— Ninguno —</option>
                                    <option
                                        v-for="product in products"
                                        :key="product.id"
                                        :value="product.id"
                                    >
                                        {{ product.name }}
                                    </option>
                                </select>

                                <Input
                                    v-model="item.description"
                                    class="w-full"
                                    placeholder="Descripción"
                                />

                                <Input
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    class="w-full text-right"
                                    placeholder="1"
                                />

                                <Input
                                    v-model.number="item.unit_price"
                                    type="number"
                                    min="0"
                                    class="w-full text-right"
                                    placeholder="0"
                                />

                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    @click="removeLooseProduct(idx)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Action buttons -->
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="startArmado"
                    >
                        <Plus class="size-4" />
                        Agregar gafas / lente
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="addLooseProduct"
                    >
                        <Plus class="size-4" />
                        Agregar producto
                    </Button>
                </div>
            </div>

            <!-- Right column: cart summary + payment + submit -->
            <div class="flex flex-col gap-4 lg:sticky lg:top-4 lg:self-start">
                <CartSummary
                    v-model:discount="cart.discount.value"
                    :armados="cart.armados.value"
                    :products="cart.products.value"
                    :subtotal="cart.subtotal.value"
                    :total="cart.total.value"
                    :surcharge-percent="cart.surchargePercent.value"
                    :balance="balance"
                    :format-c-o-p="formatCOP"
                    :discount-error="undefined"
                />

                <StepPayment
                    v-model:document-type="form.document_type"
                    v-model:show-payment="showPayment"
                    v-model:payment-method-id="paymentMethodId"
                    v-model:payment-amount="paymentAmount"
                    :payment-methods="paymentMethods"
                    :document-types="documentTypes"
                    :total="cart.total.value"
                    :format-c-o-p="formatCOP"
                    :errors="form.errors"
                />

                <!-- Notes -->
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <label for="notes" class="block text-sm font-semibold"
                        >Observaciones</label
                    >
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        placeholder="Observaciones opcionales..."
                    ></textarea>
                    <InputError :message="form.errors.notes" />
                </div>

                <!-- Submit -->
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Guardando...' : 'Guardar venta' }}
                </Button>

                <div
                    v-if="Object.keys(form.errors).length > 0"
                    class="rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    <p class="mb-1 font-medium">
                        Corrige los siguientes errores:
                    </p>
                    <ul class="list-disc space-y-0.5 pl-5">
                        <li v-for="(message, key) in form.errors" :key="key">
                            {{ message }}
                        </li>
                    </ul>
                </div>
            </div>
        </form>
    </div>
</template>
