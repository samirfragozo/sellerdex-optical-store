<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ShoppingCart, Trash2, Plus, FileText, Download, Eye } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { store } from '@/routes/pos';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { index } from '@/routes/pos';
import type { CreatedSale } from '@/types/global';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Punto de venta',
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
}

interface PaymentMethod {
    id: number;
    name: string;
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

const props = defineProps<{
    products: Product[];
    paymentMethods: PaymentMethod[];
    customers: Customer[];
}>();

const page = usePage();
const createdSale = computed<CreatedSale | null>(() => (page.props.flash as { createdSale?: CreatedSale | null })?.createdSale ?? null);

const form = useForm({
    customer_id: null as number | null,
    customer: {
        name: '',
        last_name: '',
        document_type: '',
        id_number: '',
        phone: '',
    },
    document_type: 'order',
    items: [{ product_id: null as number | null, description: '', quantity: 1, unit_price: 0 }] as OrderItem[],
    payment: null as { payment_method_id: number | null; amount: number } | null,
    discount: 0,
    notes: '',
});

// Customer mode: 'existing' | 'new'
const customerMode = ref<'existing' | 'new'>('existing');

watch(customerMode, (mode) => {
    if (mode === 'existing') {
        // Clear new customer fields
        form.customer = { name: '', last_name: '', document_type: '', id_number: '', phone: '' };
    } else {
        // Clear existing customer selection
        form.customer_id = null;
    }
});

// Payment section toggle
const showPayment = ref(false);
const paymentMethodId = ref<number | null>(null);
const paymentAmount = ref(0);

watch(showPayment, (enabled) => {
    if (enabled) {
        form.payment = { payment_method_id: paymentMethodId.value, amount: paymentAmount.value };
    } else {
        form.payment = null;
    }
});

watch([paymentMethodId, paymentAmount], () => {
    if (showPayment.value) {
        form.payment = { payment_method_id: paymentMethodId.value, amount: paymentAmount.value };
    }
});

const documentTypes = [
    { value: 'quote', label: 'Cotización' },
    { value: 'order', label: 'Pedido' },
    { value: 'layaway', label: 'Plan separe' },
    { value: 'remission', label: 'Remisión' },
    { value: 'billing', label: 'Cuenta de cobro' },
];

const subtotal = computed(() =>
    form.items.reduce((sum, item) => sum + item.quantity * item.unit_price, 0),
);

const total = computed(() => subtotal.value - (form.discount || 0));

const balance = computed(() => {
    if (!showPayment.value || !form.payment) {
        return total.value;
    }
    return total.value - (form.payment.amount || 0);
});

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

function addItem(): void {
    form.items.push({ product_id: null, description: '', quantity: 1, unit_price: 0 });
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
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.items = [{ product_id: null, description: '', quantity: 1, unit_price: 0 }];
            customerMode.value = 'existing';
            showPayment.value = false;
            paymentMethodId.value = null;
            paymentAmount.value = 0;
        },
    });
}
</script>

<template>
    <Head title="Punto de venta" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <div class="flex items-center gap-3">
            <ShoppingCart class="size-6 text-muted-foreground" />
            <h1 class="text-2xl font-semibold">Punto de venta</h1>
        </div>

        <!-- Print / download panel shown after a successful sale -->
        <div
            v-if="createdSale"
            class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/30"
        >
            <p class="mb-3 text-base font-semibold text-green-800 dark:text-green-300">
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
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-4 text-base font-semibold">Cliente</h2>

                    <!-- Toggle buttons -->
                    <div class="mb-4 flex gap-2">
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
                            class="border-input dark:bg-input/30 mt-1 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        >
                            <option :value="null">— Sin cliente —</option>
                            <option
                                v-for="customer in customers"
                                :key="customer.id"
                                :value="customer.id"
                            >
                                {{ customer.name }} {{ customer.last_name }}
                                <template v-if="customer.id_number"> — {{ customer.id_number }}</template>
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.customer_id" />
                    </div>

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
                            <InputError class="mt-1" :message="form.errors['customer.name']" />
                        </div>
                        <div>
                            <Label for="customer_last_name">Apellidos</Label>
                            <Input
                                id="customer_last_name"
                                v-model="form.customer.last_name"
                                class="mt-1 w-full"
                                placeholder="Apellidos"
                            />
                            <InputError class="mt-1" :message="form.errors['customer.last_name']" />
                        </div>
                        <div>
                            <Label for="customer_document_type">Tipo de documento</Label>
                            <select
                                id="customer_document_type"
                                v-model="form.customer.document_type"
                                class="border-input dark:bg-input/30 mt-1 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            >
                                <option value="">— Seleccionar —</option>
                                <option value="CC">Cédula de ciudadanía</option>
                                <option value="CE">Cédula de extranjería</option>
                                <option value="TI">Tarjeta de identidad</option>
                                <option value="NIT">NIT</option>
                                <option value="PP">Pasaporte</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors['customer.document_type']" />
                        </div>
                        <div>
                            <Label for="customer_id_number">Número de documento</Label>
                            <Input
                                id="customer_id_number"
                                v-model="form.customer.id_number"
                                class="mt-1 w-full"
                                placeholder="Número"
                            />
                            <InputError class="mt-1" :message="form.errors['customer.id_number']" />
                        </div>
                        <div>
                            <Label for="customer_phone">Celular</Label>
                            <Input
                                id="customer_phone"
                                v-model="form.customer.phone"
                                class="mt-1 w-full"
                                placeholder="3001234567"
                            />
                            <InputError class="mt-1" :message="form.errors['customer.phone']" />
                        </div>
                    </div>
                </div>

                <!-- Items section -->
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-4 text-base font-semibold">Ítems</h2>

                    <div class="flex flex-col gap-3">
                        <!-- Items header (desktop) -->
                        <div class="hidden grid-cols-[1fr_2fr_5rem_6rem_5rem_2rem] gap-2 text-xs font-medium text-muted-foreground sm:grid">
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
                                class="border-input dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                @change="onProductSelect(idx, ($event.target as HTMLSelectElement).value)"
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
                            <InputError :message="form.errors[`items.${idx}.description`]" />

                            <!-- Quantity -->
                            <Input
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                class="w-full text-right"
                                placeholder="1"
                            />
                            <InputError :message="form.errors[`items.${idx}.quantity`]" />

                            <!-- Unit price -->
                            <Input
                                v-model.number="item.unit_price"
                                type="number"
                                min="0"
                                class="w-full text-right"
                                placeholder="0"
                            />
                            <InputError :message="form.errors[`items.${idx}.unit_price`]" />

                            <!-- Line total -->
                            <div class="text-right text-sm font-medium tabular-nums">
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

                    <Button type="button" variant="outline" class="mt-4 gap-2" @click="addItem">
                        <Plus class="size-4" />
                        Agregar ítem
                    </Button>
                </div>
            </div>

            <!-- Right column: document type, totals, payment, notes, submit -->
            <div class="flex flex-col gap-4">

                <!-- Document type -->
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Tipo de documento</h2>
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
                    <InputError class="mt-1" :message="form.errors.document_type" />
                </div>

                <!-- Totals -->
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Resumen</h2>

                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums font-medium">{{ formatCOP(subtotal) }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2">
                            <Label for="discount" class="text-muted-foreground">Descuento</Label>
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

                        <div class="flex justify-between border-t border-border pt-2 text-base font-semibold">
                            <span>Total</span>
                            <span class="tabular-nums">{{ formatCOP(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment (abono) -->
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="mb-3 flex items-center gap-2">
                        <Checkbox
                            id="show_payment"
                            :checked="showPayment"
                            @update:checked="(v) => (showPayment = !!v)"
                        />
                        <Label for="show_payment" class="cursor-pointer font-semibold">Registrar abono</Label>
                    </div>

                    <div v-if="showPayment" class="flex flex-col gap-3">
                        <div>
                            <Label for="payment_method">Método de pago</Label>
                            <select
                                id="payment_method"
                                v-model="paymentMethodId"
                                class="border-input dark:bg-input/30 mt-1 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
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
                            <InputError :message="form.errors['payment.payment_method_id']" />
                        </div>

                        <div>
                            <Label for="payment_amount">Monto del abono</Label>
                            <Input
                                id="payment_amount"
                                v-model.number="paymentAmount"
                                type="number"
                                min="0"
                                class="mt-1 w-full text-right"
                                placeholder="0"
                            />
                            <InputError :message="form.errors['payment.amount']" />
                        </div>

                        <div class="flex justify-between border-t border-border pt-2 text-sm">
                            <span class="text-muted-foreground">Saldo pendiente</span>
                            <span
                                :class="[
                                    'tabular-nums font-semibold',
                                    balance < 0 ? 'text-destructive' : '',
                                ]"
                            >{{ formatCOP(balance) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <Label for="notes" class="font-semibold">Observaciones</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="border-input dark:bg-input/30 mt-1 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 placeholder:text-muted-foreground resize-none"
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

                <div v-if="Object.keys(form.errors).length > 0" class="rounded-md bg-destructive/10 px-4 py-2 text-sm text-destructive">
                    Por favor corrige los errores antes de guardar.
                </div>
            </div>
        </form>
    </div>
</template>
