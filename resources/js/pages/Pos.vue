<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ShoppingCart,
    Trash2,
    Plus,
    FileText,
    Download,
    Eye,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { store } from '@/routes/pos';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import DiopterInput from '@/components/DiopterInput.vue';
import { index } from '@/routes/pos';
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

interface Product {
    id: number;
    name: string;
    price: number;
    is_stockable: boolean;
    stock: number;
    category_name: string | null;
}

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

interface OrderItem {
    product_id: number | null;
    description: string;
    quantity: number;
    unit_price: number;
}

interface PrescriptionOption {
    id: number;
    customer_id: number;
    exam_date: string | null;
    lens_type: string | null;
    summary: string;
}

const props = defineProps<{
    products: Product[];
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
    items: [
        {
            product_id: null as number | null,
            description: '',
            quantity: 1,
            unit_price: 0,
        },
    ] as OrderItem[],
    payment: null as {
        payment_method_id: number | null;
        amount: number;
    } | null,
    discount: 0,
    notes: '',
    combo: {
        with_exam: false,
        forro: 'small' as 'small' | 'large',
        include_liquid: true,
        own_frame: false,
    },
    surcharge_percent: 0,
});

// Customer mode: 'none' | 'existing' | 'new'
const customerMode = ref<'none' | 'existing' | 'new'>('existing');

watch(customerMode, (mode) => {
    if (mode === 'new') {
        // Clear existing customer selection
        form.customer_id = null;
    } else {
        // Clear new customer fields ('none' and 'existing')
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

// Prescription mode: 'existing' | 'new' (only relevant when the cart has a lens)
const prescriptionMode = ref<'existing' | 'new'>('new');

// Prescriptions belonging to the currently selected existing customer.
const customerPrescriptions = computed<PrescriptionOption[]>(() =>
    form.customer_id === null
        ? []
        : props.prescriptions.filter((p) => p.customer_id === form.customer_id),
);

// A lens sale needs a customer; flag when one is missing.
const lensNeedsCustomer = computed(
    () => cartHasLens.value && customerMode.value === 'none',
);

// Force "create new" prescription when no existing one can be picked.
watch([customerMode, customerPrescriptions], () => {
    if (
        prescriptionMode.value === 'existing' &&
        customerPrescriptions.value.length === 0
    ) {
        prescriptionMode.value = 'new';
    }
});

// Payment section toggle
const showPayment = ref(false);
const paymentMethodId = ref<number | null>(null);
const paymentAmount = ref(0);

watch(showPayment, (enabled) => {
    if (enabled) {
        form.payment = {
            payment_method_id: paymentMethodId.value,
            amount: paymentAmount.value,
        };
    } else {
        form.payment = null;
    }
});

watch([paymentMethodId, paymentAmount], () => {
    if (showPayment.value) {
        form.payment = {
            payment_method_id: paymentMethodId.value,
            amount: paymentAmount.value,
        };
    }
    // Derive surcharge from the selected payment method.
    if (paymentMethodId.value !== null) {
        const pm = props.paymentMethods.find(
            (p) => p.id === paymentMethodId.value,
        );
        form.surcharge_percent = pm?.surcharge_percent ?? 0;
    } else {
        form.surcharge_percent = 0;
    }
});

const documentTypes = [
    { value: 'quote', label: 'Cotización' },
    { value: 'order', label: 'Venta' },
    { value: 'layaway', label: 'Plan separe' },
];

const subtotal = computed(() =>
    form.items.reduce((sum, item) => sum + item.quantity * item.unit_price, 0),
);

const base = computed(() => Math.max(0, subtotal.value - (form.discount || 0)));

const total = computed(() =>
    Math.round(base.value * (1 + (form.surcharge_percent || 0) / 100)),
);

const balance = computed(() => {
    if (!showPayment.value || !form.payment) {
        return total.value;
    }
    return total.value - (form.payment.amount || 0);
});

// Detect whether any cart item is a Lente category product.
const cartHasLens = computed(() =>
    form.items.some((item) => {
        if (item.product_id === null) {
            return false;
        }
        const product = props.products.find((p) => p.id === item.product_id);
        return product?.category_name === 'Lente';
    }),
);

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

function addItem(): void {
    form.items.push({
        product_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
    });
}

function removeItem(index: number): void {
    form.items.splice(index, 1);
}

function onProductSelect(index: number, productId: string): void {
    const id = productId ? parseInt(productId, 10) : null;
    const item = form.items[index];
    item.product_id = id;

    if (id !== null) {
        const product = props.products.find((p) => p.id === id);
        if (product) {
            item.description = product.name;
            item.unit_price = product.price;
        }
    }
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        // Only send the new-customer payload when actually creating one.
        customer: customerMode.value === 'new' ? data.customer : null,
        // Only send prescription data for lens sales, by the chosen mode.
        prescription_id:
            cartHasLens.value && prescriptionMode.value === 'existing'
                ? data.prescription_id
                : null,
        prescription:
            cartHasLens.value && prescriptionMode.value === 'new'
                ? data.prescription
                : null,
    })).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.items = [
                {
                    product_id: null,
                    description: '',
                    quantity: 1,
                    unit_price: 0,
                },
            ];
            form.combo = {
                with_exam: false,
                forro: 'small',
                include_liquid: true,
                own_frame: false,
            };
            form.surcharge_percent = 0;
            customerMode.value = 'existing';
            prescriptionMode.value = 'new';
            showPayment.value = false;
            paymentMethodId.value = null;
            paymentAmount.value = 0;
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
            <!-- Left column: customer + items -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <!-- Customer section -->
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-4 text-base font-semibold">Cliente</h2>

                    <!-- Toggle buttons -->
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                customerMode === 'none'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-input bg-transparent hover:bg-accent',
                            ]"
                            @click="customerMode = 'none'"
                        >
                            Sin cliente
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                customerMode === 'existing'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-input bg-transparent hover:bg-accent',
                            ]"
                            @click="customerMode = 'existing'"
                        >
                            Cliente existente
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                customerMode === 'new'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-input bg-transparent hover:bg-accent',
                            ]"
                            @click="customerMode = 'new'"
                        >
                            Cliente nuevo
                        </button>
                    </div>

                    <!-- Existing customer select -->
                    <div v-if="customerMode === 'existing'">
                        <Label for="customer_id">Seleccionar cliente</Label>
                        <select
                            id="customer_id"
                            v-model="form.customer_id"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option :value="null">— Sin cliente —</option>
                            <option
                                v-for="customer in customers"
                                :key="customer.id"
                                :value="customer.id"
                            >
                                {{ customer.name }} {{ customer.last_name }}
                                <template v-if="customer.id_number">
                                    — {{ customer.id_number }}</template
                                >
                            </option>
                        </select>
                        <InputError
                            class="mt-1"
                            :message="form.errors.customer_id"
                        />
                    </div>

                    <!-- No customer -->
                    <p
                        v-else-if="customerMode === 'none'"
                        class="text-sm text-muted-foreground"
                    >
                        La venta se registrará sin cliente.
                    </p>

                    <!-- New customer fields -->
                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label for="customer_name">Nombre</Label>
                            <Input
                                id="customer_name"
                                v-model="form.customer.name"
                                class="mt-1 w-full"
                                placeholder="Nombre"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.name']"
                            />
                        </div>
                        <div>
                            <Label for="customer_last_name">Apellidos</Label>
                            <Input
                                id="customer_last_name"
                                v-model="form.customer.last_name"
                                class="mt-1 w-full"
                                placeholder="Apellidos"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.last_name']"
                            />
                        </div>
                        <div>
                            <Label for="customer_document_type"
                                >Tipo de documento</Label
                            >
                            <select
                                id="customer_document_type"
                                v-model="form.customer.document_type"
                                class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                            >
                                <option value="">— Seleccionar —</option>
                                <option value="cc">Cédula de ciudadanía</option>
                                <option value="ce">
                                    Cédula de extranjería
                                </option>
                                <option value="ti">Tarjeta de identidad</option>
                                <option value="nit">NIT</option>
                                <option value="pa">Pasaporte</option>
                            </select>
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.document_type']"
                            />
                        </div>
                        <div>
                            <Label for="customer_id_number"
                                >Número de documento</Label
                            >
                            <Input
                                id="customer_id_number"
                                v-model="form.customer.id_number"
                                class="mt-1 w-full"
                                placeholder="Número"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.id_number']"
                            />
                        </div>
                        <div>
                            <Label for="customer_phone">Celular</Label>
                            <Input
                                id="customer_phone"
                                v-model="form.customer.phone"
                                class="mt-1 w-full"
                                placeholder="3001234567"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.phone']"
                            />
                        </div>
                        <div>
                            <Label for="customer_email">Correo</Label>
                            <Input
                                id="customer_email"
                                v-model="form.customer.email"
                                type="email"
                                class="mt-1 w-full"
                                placeholder="correo@ejemplo.com"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.email']"
                            />
                        </div>
                        <div>
                            <Label for="customer_birth_date">Fecha de nacimiento</Label>
                            <Input
                                id="customer_birth_date"
                                v-model="form.customer.birth_date"
                                type="date"
                                :max="today"
                                class="mt-1 w-full"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.birth_date']"
                            />
                        </div>
                        <div>
                            <Label for="customer_address">Dirección</Label>
                            <Input
                                id="customer_address"
                                v-model="form.customer.address"
                                class="mt-1 w-full"
                                placeholder="Calle 1 # 2-3"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.address']"
                            />
                        </div>
                        <div>
                            <Label for="customer_city">Ciudad</Label>
                            <Input
                                id="customer_city"
                                v-model="form.customer.city"
                                class="mt-1 w-full"
                                placeholder="Ciudad"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.city']"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label for="customer_notes">Notas</Label>
                            <textarea
                                id="customer_notes"
                                v-model="form.customer.notes"
                                rows="2"
                                class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                                placeholder="Opcional..."
                            ></textarea>
                            <InputError
                                class="mt-1"
                                :message="form.errors['customer.notes']"
                            />
                        </div>
                    </div>
                </div>

                <!-- Items section -->
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-4 text-base font-semibold">Ítems</h2>

                    <div class="flex flex-col gap-3">
                        <!-- Items header (desktop) -->
                        <div
                            class="hidden grid-cols-[1fr_2fr_5rem_6rem_5rem_2rem] gap-2 text-xs font-medium text-muted-foreground sm:grid"
                        >
                            <span>Producto</span>
                            <span>Descripción</span>
                            <span class="text-right">Cant.</span>
                            <span class="text-right">P. unitario</span>
                            <span class="text-right">Total</span>
                            <span></span>
                        </div>

                        <!-- Item rows -->
                        <div
                            v-for="(item, idx) in form.items"
                            :key="idx"
                            class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_2fr_5rem_6rem_5rem_2rem] sm:items-center"
                        >
                            <!-- Product select -->
                            <select
                                :value="item.product_id ?? ''"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                                @change="
                                    onProductSelect(
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

                            <!-- Description -->
                            <Input
                                v-model="item.description"
                                class="w-full"
                                placeholder="Descripción"
                            />
                            <InputError
                                :message="
                                    form.errors[`items.${idx}.description`]
                                "
                            />

                            <!-- Quantity -->
                            <Input
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                class="w-full text-right"
                                placeholder="1"
                            />
                            <InputError
                                :message="form.errors[`items.${idx}.quantity`]"
                            />

                            <!-- Unit price -->
                            <Input
                                v-model.number="item.unit_price"
                                type="number"
                                min="0"
                                class="w-full text-right"
                                placeholder="0"
                            />
                            <InputError
                                :message="
                                    form.errors[`items.${idx}.unit_price`]
                                "
                            />

                            <!-- Line total -->
                            <div
                                class="text-right text-sm font-medium tabular-nums"
                            >
                                {{ formatCOP(item.quantity * item.unit_price) }}
                            </div>

                            <!-- Remove button -->
                            <button
                                type="button"
                                class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive disabled:opacity-30"
                                :disabled="form.items.length === 1"
                                @click="removeItem(idx)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>

                        <InputError :message="form.errors.items" />
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        class="mt-4 gap-2"
                        @click="addItem"
                    >
                        <Plus class="size-4" />
                        Agregar ítem
                    </Button>
                </div>

                <!-- Prescription card: required when a Lente is in the cart -->
                <div
                    v-if="cartHasLens"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-1 text-base font-semibold">Prescripción</h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Obligatoria para vender lentes formulados.
                    </p>

                    <!-- A lens sale requires a customer -->
                    <div
                        v-if="lensNeedsCustomer"
                        class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
                    >
                        Para vender lentes formulados debes seleccionar o
                        registrar un cliente.
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
                            <Label for="prescription_id"
                                >Seleccionar prescripción</Label
                            >
                            <select
                                id="prescription_id"
                                v-model="form.prescription_id"
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
                                :message="form.errors.prescription_id"
                            />
                        </div>

                        <!-- New prescription inline form -->
                        <div v-else class="flex flex-col gap-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label for="rx_exam_date"
                                        >Fecha del examen</Label
                                    >
                                    <Input
                                        id="rx_exam_date"
                                        v-model="form.prescription.exam_date"
                                        type="date"
                                        :min="minExamDate"
                                        :max="today"
                                        class="mt-1 w-full"
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            form.errors[
                                                'prescription.exam_date'
                                            ]
                                        "
                                    />
                                </div>
                                <div>
                                    <Label for="rx_lens_type"
                                        >Tipo de lente</Label
                                    >
                                    <select
                                        id="rx_lens_type"
                                        v-model="form.prescription.lens_type"
                                        class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                                    >
                                        <option value="">
                                            — Seleccionar —
                                        </option>
                                        <option
                                            v-for="(label, value) in lensTypes"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
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
                                        v-model="form.prescription.od_sphere"
                                        :min="0"
                                        :max="20"
                                    />
                                    <DiopterInput
                                        v-model="form.prescription.od_cylinder"
                                        :min="0"
                                        :max="10"
                                    />
                                    <Input
                                        v-model="form.prescription.od_axis"
                                        type="number"
                                        min="1"
                                        max="180"
                                        class="w-full text-right"
                                    />
                                    <DiopterInput
                                        v-model="form.prescription.od_add"
                                        fixed-sign="+"
                                        :min="0"
                                        :max="4"
                                    />
                                </div>
                                <div
                                    class="mt-2 grid min-w-[26rem] grid-cols-[2rem_1fr_1fr_1fr_1fr] items-center gap-2"
                                >
                                    <span class="text-sm font-medium">OS</span>
                                    <DiopterInput
                                        v-model="form.prescription.os_sphere"
                                        :min="0"
                                        :max="20"
                                    />
                                    <DiopterInput
                                        v-model="form.prescription.os_cylinder"
                                        :min="0"
                                        :max="10"
                                    />
                                    <Input
                                        v-model="form.prescription.os_axis"
                                        type="number"
                                        min="1"
                                        max="180"
                                        class="w-full text-right"
                                    />
                                    <DiopterInput
                                        v-model="form.prescription.os_add"
                                        fixed-sign="+"
                                        :min="0"
                                        :max="4"
                                    />
                                </div>
                            </div>

                            <div>
                                <Label for="rx_diagnosis">Diagnóstico</Label>
                                <textarea
                                    id="rx_diagnosis"
                                    v-model="form.prescription.diagnosis"
                                    rows="2"
                                    class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                                    placeholder="Opcional..."
                                ></textarea>
                            </div>
                        </div>
                    </template>

                    <InputError
                        class="mt-2"
                        :message="form.errors.prescription"
                    />
                </div>

                <!-- Combo card: shown only when a Lente is in the cart -->
                <div
                    v-if="cartHasLens"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-4 text-base font-semibold">Combo</h2>
                    <div class="flex flex-col gap-3">
                        <!-- Examen -->
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <Checkbox v-model="form.combo.with_exam" />
                            Incluir examen (gratis)
                        </label>

                        <!-- Forro -->
                        <div>
                            <span class="mb-1 block text-sm font-medium"
                                >Forro</span
                            >
                            <div class="flex gap-4">
                                <label
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        v-model="form.combo.forro"
                                        type="radio"
                                        value="small"
                                        class="size-4 accent-primary"
                                    />
                                    Pequeño
                                </label>
                                <label
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        v-model="form.combo.forro"
                                        type="radio"
                                        value="large"
                                        class="size-4 accent-primary"
                                    />
                                    Grande
                                </label>
                            </div>
                        </div>

                        <!-- Líquido -->
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <Checkbox v-model="form.combo.include_liquid" />
                            Incluir líquido
                        </label>

                        <!-- Montura propia -->
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <Checkbox v-model="form.combo.own_frame" />
                            El cliente trae su montura
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right column: document type, totals, payment, notes, submit -->
            <div class="flex flex-col gap-4">
                <!-- Document type -->
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-3 text-base font-semibold">
                        Tipo de documento
                    </h2>
                    <div class="flex flex-col gap-2">
                        <label
                            v-for="dt in documentTypes"
                            :key="dt.value"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm transition-colors hover:bg-accent"
                        >
                            <input
                                v-model="form.document_type"
                                type="radio"
                                :value="dt.value"
                                class="size-4 accent-primary"
                            />
                            {{ dt.label }}
                        </label>
                    </div>
                    <InputError
                        class="mt-1"
                        :message="form.errors.document_type"
                    />
                </div>

                <!-- Totals -->
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-3 text-base font-semibold">Resumen</h2>

                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-medium tabular-nums">{{
                                formatCOP(subtotal)
                            }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2">
                            <Label for="discount" class="text-muted-foreground"
                                >Descuento</Label
                            >
                            <Input
                                id="discount"
                                v-model.number="form.discount"
                                type="number"
                                min="0"
                                class="w-28 text-right"
                                placeholder="0"
                            />
                        </div>
                        <InputError :message="form.errors.discount" />

                        <!-- Surcharge note -->
                        <div
                            v-if="form.surcharge_percent > 0"
                            class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
                        >
                            Total incluye recargo de plataforma ({{
                                form.surcharge_percent
                            }}%)
                        </div>

                        <div
                            class="flex justify-between border-t border-border pt-2 text-base font-semibold"
                        >
                            <span>Total</span>
                            <span class="tabular-nums">{{
                                formatCOP(total)
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment (abono) -->
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <Checkbox id="show_payment" v-model="showPayment" />
                        <Label
                            for="show_payment"
                            class="cursor-pointer font-semibold"
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
                                :message="
                                    form.errors['payment.payment_method_id']
                                "
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
                            <InputError
                                :message="form.errors['payment.amount']"
                            />
                            <p
                                v-if="paymentAmount > total"
                                class="mt-1 text-sm text-destructive"
                            >
                                El abono no puede superar el total ({{ formatCOP(total) }}).
                            </p>
                        </div>

                        <div
                            class="flex justify-between border-t border-border pt-2 text-sm"
                        >
                            <span class="text-muted-foreground"
                                >Saldo pendiente</span
                            >
                            <span
                                :class="[
                                    'font-semibold tabular-nums',
                                    balance < 0 ? 'text-destructive' : '',
                                ]"
                                >{{ formatCOP(balance) }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <Label for="notes" class="font-semibold"
                        >Observaciones</Label
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
