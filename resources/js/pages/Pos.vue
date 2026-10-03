<script setup lang="ts">
import { Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ArmadoModal from '@/components/pos/ArmadoModal.vue';
import CartItemRow from '@/components/pos/CartItemRow.vue';
import CartSummary from '@/components/pos/CartSummary.vue';
import CashSessionGateModal from '@/components/pos/CashSessionGateModal.vue';
import CheckoutModal from '@/components/pos/CheckoutModal.vue';
import ProductCatalog from '@/components/pos/ProductCatalog.vue';
import ReadinessBanner from '@/components/pos/ReadinessBanner.vue';
import ReadinessBlockingDialog from '@/components/pos/ReadinessBlockingDialog.vue';
import SaleCreatedPanel from '@/components/pos/SaleCreatedPanel.vue';
import StepCustomer from '@/components/pos/StepCustomer.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCashRegisterSession } from '@/composables/useCashRegisterSession';
import type {
    LensCatalogProp,
    PaginatedProducts,
    ProductProp,
} from '@/composables/useLensCatalog';
import type { Armado, KitProp } from '@/composables/usePosCart';
import { armadoTotal, usePosCart } from '@/composables/usePosCart';
import { usePosCheckout } from '@/composables/usePosCheckout';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { index } from '@/routes/pos';
import { prescriptions as customerPrescriptions } from '@/routes/pos/customers';
import type { PrescriptionOption } from '@/types';
import type { CreatedSale, ReadinessIssue } from '@/types/global';

const { trans } = useTranslations();

setLayoutProps({
    breadcrumbs: [{ title: trans('app.pos.title'), href: index() }],
});

interface PaymentMethod {
    id: number;
    name: string;
    surcharge_percent: number;
}

const props = defineProps<{
    products: PaginatedProducts;
    armadoProducts: ProductProp[];
    lensCatalog: LensCatalogProp;
    kit: KitProp;
    categories: { id: number; name: string; key: string }[];
    paymentMethods: PaymentMethod[];
}>();

const today = new Date().toISOString().slice(0, 10);
const minExamDate = (() => {
    const d = new Date();
    d.setFullYear(d.getFullYear() - 2);

    return d.toISOString().slice(0, 10);
})();

// --- Cash register session gate ---
const { session, onSessionOpened } = useCashRegisterSession();

// --- Sale readiness (banner for warnings, modals for blockers) ---
const page = usePage();
const readiness = computed<ReadinessIssue[]>(() => page.props.readiness ?? []);
const globalBlockers = computed(() =>
    readiness.value.filter(
        (i) => i.severity === 'blocking' && i.scope === null,
    ),
);
const lensBlockers = computed(() =>
    readiness.value.filter(
        (i) => i.severity === 'blocking' && i.scope === 'lens',
    ),
);
const globalBlockingOpen = ref(globalBlockers.value.length > 0);
const lensBlockingOpen = ref(false);

// --- Cart ---
const cart = usePosCart(props.kit);

// --- Customer (fixed panel, no longer a collapsible step) ---
const customerId = ref<number | null>(null);
const customerLabel = ref('');

// Grown per patient as the armado wizard picks one (and as prescriptions
// are created) — no page-wide list with a cap.
const prescriptions = ref<PrescriptionOption[]>([]);

async function loadPrescriptions(customerId: number): Promise<void> {
    const response = await csrfFetch(customerPrescriptions.url(customerId));

    if (!response.ok) {
        return;
    }

    const loaded = (await response.json()) as PrescriptionOption[];
    prescriptions.value = [
        ...loaded,
        ...prescriptions.value.filter((p) => p.customer_id !== customerId),
    ];
}

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
    if (id === null && lensBlockers.value.length > 0) {
        lensBlockingOpen.value = true;

        return;
    }

    editingArmadoId.value = id;
    armadoModalOpen.value = true;
}

// A prescription saved from the wizard's "new" form is already selected
// (the modal sets the armado's prescription itself) — just grow the shared list
// so it shows up as an "existing" option without a page reload.
function onPrescriptionSaved(prescription: PrescriptionOption): void {
    prescriptions.value = [prescription, ...prescriptions.value];
}

// A new armado defaults to the last one's prescription, when it was for the
// payer.
const suggestedPrescriptionId = computed<number | null>(
    () =>
        cart.armados.value.findLast((a) => a.patient_id === customerId.value)
            ?.prescription_id ?? null,
);

function onArmadoSave(armado: Armado): void {
    const data = {
        patient_id: armado.patient_id,
        patient_name: armado.patient_name,
        prescription_id: armado.prescription_id,
        lens: armado.lens,
        frame: armado.frame,
        own_frame: armado.own_frame,
        slots: armado.slots,
    };

    if (editingArmadoId.value !== null) {
        cart.updateArmado(editingArmadoId.value, data);
    } else {
        cart.commitArmado(data);
    }

    armadoModalOpen.value = false;
}

// Manual price override for an armado's total. The lens line absorbs the
// edit (frame, slot lines and `added_to_lens` surcharges keep their price,
// as RegisterSale adds them on top of the override) — stored separately
// from the lens price so it is dropped whenever the armado is reconfigured
// through the wizard (StepLens rebuilds the lens line from scratch).
function updateArmadoTotal(armado: Armado, value: number): void {
    if (!armado.lens) {
        return;
    }

    const others =
        armadoTotal(armado, props.kit) -
        (armado.lens.price_override ?? armado.lens.price);
    armado.lens.price_override = Math.max(0, value - others);
}

// --- Catalog -> cart wiring ---
function onAddProduct(product: ProductProp): void {
    cart.addOrIncrementProduct({
        id: product.id,
        name: product.name,
        price: product.price,
    });
}

// A product with option groups (e.g. a frame) resolves to a variant via the
// catalog's picker dialog instead of being added as-is.
function onAddResolvedProduct(resolved: {
    id: number;
    name: string;
    price: number;
}): void {
    cart.addOrIncrementProduct(resolved);
}

function removeLooseProduct(index: number): void {
    cart.removeProduct(index);
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
        armados: cartPayload.armados,
        products: cartPayload.products,
        discount_percent: cart.discountPercent.value,
        surcharge_percent: cart.surchargePercent.value,
    });

    if (result === null) {
        return;
    }

    createdSale.value = result;
    checkoutModalOpen.value = false;
    checkout.reset();
    cart.armados.value = [];
    cart.products.value = [];
    cart.discountPercent.value = 0;
    cart.surchargePercent.value = 0;
    customerId.value = null;
    customerLabel.value = '';
}
</script>

<template>
    <Head :title="trans('app.pos.title')" />

    <CashSessionGateModal :open="session === null" @opened="onSessionOpened" />

    <ReadinessBanner :issues="readiness" />
    <ReadinessBlockingDialog
        v-model:open="globalBlockingOpen"
        :issues="globalBlockers"
        :title="trans('app.readiness.blocking_title')"
    />
    <ReadinessBlockingDialog
        v-model:open="lensBlockingOpen"
        :issues="lensBlockers"
        :title="trans('app.readiness.blocking_lens_title')"
    />

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
                @add-resolved-product="onAddResolvedProduct"
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
                <div
                    v-if="showMobileCart"
                    class="flex items-center justify-end gap-3 md:hidden"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
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

                <StepCustomer
                    v-model:customer-id="customerId"
                    v-model:selected-label="customerLabel"
                    :today="today"
                />

                <div
                    v-if="
                        cart.armados.value.length === 0 &&
                        cart.products.value.length === 0
                    "
                    class="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-6 text-center dark:border-sidebar-border"
                >
                    <ShoppingCart class="size-6 text-muted-foreground" />
                    <p class="text-sm font-medium">
                        {{ trans('app.pos.empty_cart.title') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ trans('app.pos.empty_cart.description') }}
                    </p>
                </div>

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
                            <p
                                v-if="armado.patient_name"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{
                                    trans('app.pos.armado_patient').replace(
                                        ':name',
                                        armado.patient_name,
                                    )
                                }}
                            </p>
                        </div>
                        <Input
                            :model-value="armadoTotal(armado, kit)"
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
                                @click="cart.removeArmado(armado.id)"
                            >
                                {{ trans('app.pos.remove_armado') }}
                            </Button>
                        </div>
                    </div>
                </template>

                <ArmadoModal
                    :open="armadoModalOpen"
                    :armado="editingArmado"
                    :lens-catalog="lensCatalog"
                    :kit="kit"
                    :frame-products="frameProducts"
                    :prescriptions="prescriptions"
                    :suggested-prescription-id="suggestedPrescriptionId"
                    :today="today"
                    :min-exam-date="minExamDate"
                    v-model:customer-label="customerLabel"
                    v-model:customer-id="customerId"
                    @update:open="armadoModalOpen = $event"
                    @save="onArmadoSave"
                    @saved="onPrescriptionSaved"
                    @patient-selected="loadPrescriptions"
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
                    :armados="cart.armados.value"
                    :kit="kit"
                    :products="cart.products.value"
                    :subtotal="cart.subtotal.value"
                    :total="cart.total.value"
                    :discount-amount="cart.discountAmount.value"
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
