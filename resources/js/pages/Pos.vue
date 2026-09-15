<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ArmadoModal from '@/components/pos/ArmadoModal.vue';
import CartItemRow from '@/components/pos/CartItemRow.vue';
import CartSummary from '@/components/pos/CartSummary.vue';
import CashSessionGateModal from '@/components/pos/CashSessionGateModal.vue';
import CheckoutModal from '@/components/pos/CheckoutModal.vue';
import ProductCatalog from '@/components/pos/ProductCatalog.vue';
import SaleCreatedPanel from '@/components/pos/SaleCreatedPanel.vue';
import StepCustomer from '@/components/pos/StepCustomer.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCashRegisterSession } from '@/composables/useCashRegisterSession';
import type {
    LensProduct,
    LensSpecs,
    PaginatedProducts,
    ProductProp,
} from '@/composables/useLensCatalog';
import { useLensRecommendation } from '@/composables/useLensRecommendation';
import type { Armado } from '@/composables/usePosCart';
import { armadoTotal, usePosCart } from '@/composables/usePosCart';
import { usePosCheckout } from '@/composables/usePosCheckout';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/pos';
import type { CreatedSale } from '@/types/global';

const { trans } = useTranslations();

setLayoutProps({
    breadcrumbs: [{ title: trans('app.pos.title'), href: index() }],
});

interface PaymentMethod {
    id: number;
    name: string;
    surcharge_percent: number;
}

interface PrescriptionOption {
    id: number;
    customer_id: number;
    exam_date: string | null;
    lens_type: string | null;
    summary: string;
}

const props = defineProps<{
    products: PaginatedProducts;
    armadoProducts: ProductProp[];
    categories: { id: number; name: string; key: string }[];
    paymentMethods: PaymentMethod[];
    prescriptions: PrescriptionOption[];
    lensTypes: Record<string, string>;
}>();

const today = new Date().toISOString().slice(0, 10);
const minExamDate = (() => {
    const d = new Date();
    d.setFullYear(d.getFullYear() - 2);

    return d.toISOString().slice(0, 10);
})();

// --- Cash register session gate ---
const { session, onSessionOpened } = useCashRegisterSession();

// --- Cart + recommendation composables ---
const cart = usePosCart();
const { recommended, warnings, fetchFor } = useLensRecommendation();

const lensSelections = ref<Record<number, LensSpecs>>({});
const resolvedLenses = ref<Record<number, LensProduct | null>>({});

// --- Customer (fixed panel, no longer a collapsible step) ---
const customerId = ref<number | null>(null);

// Seeded from the page prop, then grown locally as prescriptions are
// created on the fly during checkout — no full page reload needed.
const prescriptions = ref<PrescriptionOption[]>([...props.prescriptions]);

// --- Prescription mode (used inside the armado modal) ---
const prescriptionMode = ref<'existing' | 'new'>('new');
const prescriptionId = ref<number | null>(null);
const newPrescription = ref({
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
});

const customerPrescriptions = computed<PrescriptionOption[]>(() =>
    customerId.value === null
        ? []
        : prescriptions.value.filter((p) => p.customer_id === customerId.value),
);

const lensNeedsCustomer = computed(() => customerId.value === null);

watch(customerPrescriptions, () => {
    if (
        prescriptionMode.value === 'existing' &&
        customerPrescriptions.value.length === 0
    ) {
        prescriptionMode.value = 'new';
    }
});

// --- Document types ---
const documentTypes = [
    { value: 'quote', label: trans('app.sale_document_type.quote') },
    { value: 'order', label: trans('app.sale_document_type.order') },
    { value: 'layaway', label: trans('app.sale_document_type.layaway') },
];

// --- Frame products (used inside the armado modal) ---
// Sourced from armadoProducts (unpaginated, option groups intact) rather
// than products.data: the general catalog grid excludes option-group
// products, but the armado wizard needs them to offer color/filter picks.
const frameProducts = computed<ProductProp[]>(() =>
    props.armadoProducts.filter((p) => p.category_key === 'frame'),
);

// Lenses are only selectable through the armado wizard, not as loose
// products — otherwise the customer/prescription requirement is bypassed
// (StorePosSaleRequest's cartHasLens() only inspects the armados array).
const looseProducts = computed<ProductProp[]>(() =>
    props.products.data.filter((p) => p.category_key !== 'lens'),
);

// --- Armado management ---
const editingArmadoId = ref<number | null>(null);
const armadoModalOpen = ref(false);

const editingArmado = computed<Armado | null>(
    () =>
        cart.armados.value.find((a) => a.id === editingArmadoId.value) ?? null,
);

function openArmadoModal(id: number | null): void {
    editingArmadoId.value = id;
    armadoModalOpen.value = true;
}

function onArmadoSave(payload: {
    armado: Armado;
    lensSelection: LensSpecs;
    resolvedLens: LensProduct | null;
}): void {
    const data = {
        lens: payload.armado.lens,
        frame: payload.armado.frame,
        own_frame: payload.armado.own_frame,
        combo: payload.armado.combo,
    };
    const id = editingArmadoId.value ?? cart.commitArmado(data).id;

    if (editingArmadoId.value !== null) {
        cart.updateArmado(editingArmadoId.value, data);
    }

    lensSelections.value[id] = payload.lensSelection;
    resolvedLenses.value[id] = payload.resolvedLens;
    armadoModalOpen.value = false;
}

function removeArmado(id: number): void {
    cart.removeArmado(id);
    delete lensSelections.value[id];
    delete resolvedLenses.value[id];
}

// Manual price override for an armado's total. The frame is always $0
// inside a combo (see RegisterSale), so the lens line absorbs the edit —
// stored separately from unit_price so it survives re-render without
// masking the underlying computed price, and is dropped whenever the
// armado is reconfigured through the wizard (see ArmadoModal's
// onLensSelectionChange, which rebuilds the lens line from scratch).
function updateArmadoTotal(armado: Armado, value: number): void {
    if (!armado.lens) {
        return;
    }

    const frameContribution = !armado.own_frame
        ? (armado.frame?.unit_price ?? 0)
        : 0;
    armado.lens.price_override = Math.max(0, value - frameContribution);
}

// --- Catalog -> cart wiring ---
function onAddProduct(product: ProductProp): void {
    cart.addOrIncrementProduct({
        id: product.id,
        name: product.name,
        price: product.price,
        tax_rate: product.tax_rate,
    });
}

function removeLooseProduct(index: number): void {
    cart.removeProduct(index);
}

function onRefreshRecommendation(sel: {
    design?: string;
    material?: string;
}): void {
    void fetchFor(newPrescription.value as Record<string, unknown>, sel);
}

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

// --- Checkout ---
const checkout = usePosCheckout(cart.total);
const checkoutModalOpen = ref(false);
const showMobileCart = ref(false);
const createdSale = ref<CreatedSale | null>(null);

// Mirrors the backend's weighted-average calculation
// (RegisterSale::resolveSurcharge()) so the cart total/summary reflect the
// real charge before submitting, since an explicit surcharge_percent in the
// payload always wins over the backend's own computation.
watch(
    () => checkout.payments.value,
    (payments) => {
        const active = payments.filter((p) => p.amount > 0);
        const total = active.reduce((sum, p) => sum + p.amount, 0);

        if (total === 0) {
            cart.surchargePercent.value = 0;

            return;
        }

        const weighted = active.reduce((sum, p) => {
            const method = props.paymentMethods.find(
                (m) => m.id === p.payment_method_id,
            );

            return sum + (method?.surcharge_percent ?? 0) * p.amount;
        }, 0);

        cart.surchargePercent.value = weighted / total;
    },
    { deep: true },
);

async function confirmCheckout(): Promise<void> {
    const cartPayload = cart.buildPayload();

    const result = await checkout.submit({
        customer_id: customerId.value,
        customer: null,
        prescription_id:
            cart.armados.value.length > 0 &&
            prescriptionMode.value === 'existing'
                ? prescriptionId.value
                : null,
        prescription:
            cart.armados.value.length > 0 && prescriptionMode.value === 'new'
                ? newPrescription.value
                : null,
        armados: cartPayload.armados,
        products: cartPayload.products,
        discount_percent: cart.discountPercent.value,
        tip_percent: cart.tipPercent.value,
        surcharge_percent: cart.surchargePercent.value,
    });

    if (result === null) {
        return;
    }

    if (
        result.prescription_id !== null &&
        prescriptionMode.value === 'new' &&
        customerId.value !== null
    ) {
        prescriptions.value = [
            {
                id: result.prescription_id,
                customer_id: customerId.value,
                exam_date: newPrescription.value.exam_date,
                lens_type: newPrescription.value.lens_type,
                summary: `OD ${newPrescription.value.od_sphere || '—'} / OS ${newPrescription.value.os_sphere || '—'}`,
            },
            ...prescriptions.value,
        ];
    }

    createdSale.value = result;
    checkoutModalOpen.value = false;
    checkout.reset();
    cart.armados.value = [];
    cart.products.value = [];
    cart.discountPercent.value = 0;
    cart.tipPercent.value = 0;
    cart.surchargePercent.value = 0;
    lensSelections.value = {};
    resolvedLenses.value = {};
    customerId.value = null;
    prescriptionMode.value = 'new';
    prescriptionId.value = null;
    newPrescription.value = {
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
    };
}
</script>

<template>
    <Head :title="trans('app.pos.title')" />

    <CashSessionGateModal :open="session === null" @opened="onSessionOpened" />

    <div class="flex h-full flex-1 overflow-hidden">
        <!-- Catalog -->
        <div
            :class="[
                'h-full min-h-0 w-full min-w-sm flex-col transition-all duration-300',
                showMobileCart ? 'hidden md:flex' : 'flex',
            ]"
        >
            <ProductCatalog
                :products="products"
                :categories="categories"
                @select-lens-category="openArmadoModal(null)"
                @add-product="onAddProduct"
            />
        </div>

        <!-- Cart -->
        <div
            :class="[
                'h-full min-h-0 min-w-sm flex-col border-l border-sidebar-border/70 dark:border-sidebar-border',
                showMobileCart ? 'flex w-full' : 'hidden md:flex',
            ]"
        >
            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="size-5 text-muted-foreground" />
                        <h1 class="text-lg font-semibold">
                            {{ trans('app.pos.title') }}
                        </h1>
                    </div>
                    <Button
                        v-if="showMobileCart"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="md:hidden"
                        @click="showMobileCart = false"
                    >
                        {{ trans('app.pos.catalog.all_categories') }}
                    </Button>
                </div>

                <SaleCreatedPanel
                    v-if="createdSale"
                    :sale="createdSale"
                    @dismiss="createdSale = null"
                />

                <StepCustomer v-model:customer-id="customerId" :today="today" />

                <template v-if="cart.armados.value.length > 0">
                    <div
                        v-for="armado in cart.armados.value"
                        :key="armado.id"
                        class="flex items-center justify-between gap-2 rounded-xl border border-sidebar-border/70 bg-white p-3 dark:border-sidebar-border dark:bg-zinc-900"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ armado.lens?.description }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    armado.own_frame
                                        ? trans('app.pos.summary.own_frame')
                                        : (armado.frame?.description ??
                                          trans('app.pos.none_option'))
                                }}
                            </p>
                        </div>
                        <Input
                            :model-value="armadoTotal(armado)"
                            type="number"
                            min="0"
                            class="w-28 shrink-0 text-right"
                            :aria-label="trans('app.pos.edit_armado_price')"
                            @update:model-value="
                                (value) =>
                                    updateArmadoTotal(armado, Number(value))
                            "
                        />
                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="openArmadoModal(armado.id)"
                            >
                                {{ trans('app.pos.edit_armado') }}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="removeArmado(armado.id)"
                            >
                                {{ trans('app.pos.remove_armado') }}
                            </Button>
                        </div>
                    </div>
                </template>

                <ArmadoModal
                    :open="armadoModalOpen"
                    :armado="editingArmado"
                    :lens-selection="
                        editingArmadoId !== null
                            ? (lensSelections[editingArmadoId] ?? null)
                            : null
                    "
                    :resolved-lens="
                        editingArmadoId !== null
                            ? (resolvedLenses[editingArmadoId] ?? null)
                            : null
                    "
                    :products="armadoProducts"
                    :frame-products="frameProducts"
                    :recommended="recommended"
                    :warnings="warnings"
                    :customer-prescriptions="customerPrescriptions"
                    :lens-needs-customer="lensNeedsCustomer"
                    :today="today"
                    :min-exam-date="minExamDate"
                    v-model:prescription-mode="prescriptionMode"
                    v-model:prescription-id="prescriptionId"
                    v-model:prescription="newPrescription"
                    @update:open="armadoModalOpen = $event"
                    @refresh-recommendation="onRefreshRecommendation"
                    @save="onArmadoSave"
                />

                <div
                    v-if="cart.products.value.length > 0"
                    class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-white p-3 dark:border-sidebar-border dark:bg-zinc-900"
                >
                    <CartItemRow
                        v-for="(item, idx) in cart.products.value"
                        :key="idx"
                        :item="item"
                        :products="looseProducts"
                        @update:item="
                            (value) => (cart.products.value[idx] = value)
                        "
                        @remove="removeLooseProduct(idx)"
                    />
                </div>
            </div>

            <div
                class="flex shrink-0 flex-col gap-4 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <CartSummary
                    v-model:discount-percent="cart.discountPercent.value"
                    v-model:tip-percent="cart.tipPercent.value"
                    :armados="cart.armados.value"
                    :products="cart.products.value"
                    :subtotal="cart.subtotal.value"
                    :total="cart.total.value"
                    :discount-amount="cart.discountAmount.value"
                    :tax-amount="cart.taxAmount.value"
                    :tip-amount="cart.tipAmount.value"
                    :surcharge-percent="cart.surchargePercent.value"
                    :balance="cart.total.value"
                    :discount-error="checkout.errors.value.discount_percent"
                    :format-c-o-p="formatCOP"
                />

                <Button
                    type="button"
                    class="w-full"
                    :disabled="
                        (cart.armados.value.length === 0 &&
                            cart.products.value.length === 0) ||
                        session === null
                    "
                    @click="checkoutModalOpen = true"
                >
                    {{ trans('app.pos.checkout.title') }}
                </Button>
            </div>
        </div>

        <!-- Mobile cart toggle -->
        <button
            v-if="!showMobileCart"
            type="button"
            class="fixed right-4 bottom-4 flex items-center gap-2 rounded-full bg-primary px-4 py-3 text-sm font-medium text-primary-foreground shadow-lg md:hidden"
            @click="showMobileCart = true"
        >
            <ShoppingCart class="size-4" />
            {{ trans('app.pos.view_cart') }}
            ({{ cart.armados.value.length + cart.products.value.length }})
        </button>
    </div>

    <CheckoutModal
        :open="checkoutModalOpen"
        :payment-methods="paymentMethods"
        :document-types="documentTypes"
        :total="cart.total.value"
        :checkout="checkout"
        @update:open="checkoutModalOpen = $event"
        @confirm="confirmCheckout"
    />
</template>
