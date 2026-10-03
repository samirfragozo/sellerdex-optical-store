import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export interface ArmadoLine {
    product_id: number;
    description: string;
    unit_price: number;
    unit_cost?: number;
    option_ids?: number[];
    /** Manual price override for the armado total, set from the cart row. */
    price_override?: number;
}

/** A lens line is configured through the lens catalog, not a product. */
export interface ArmadoLensLine {
    description: string;
    quantity: number;
    lens_type_id: number;
    lens_technology_id: number;
    lens_material_id: number;
    treatment_ids: number[];
    /** Resolved client-side for the cart preview only; the backend recomputes it. */
    price: number;
    cost: number;
    /** Manual price override for the armado total, set from the cart row. */
    price_override?: number;
}

/** One product slot of the armado combo, as shared by PosController::kit(). */
export interface KitSlotProp {
    id: number;
    category_name: string | null;
    quantity: number;
    price_mode: 'free' | 'normal' | 'discount_percent' | 'added_to_lens';
    price_value: number;
    is_optional: boolean;
    is_preselected: boolean;
    default_product_id: number;
    products: { id: number; name: string; price: number }[];
}

export interface KitProp {
    frame_price_mode: 'included' | 'normal' | 'discount_percent';
    frame_discount_percent: number;
    armado_slots: KitSlotProp[];
}

export interface ArmadoSlot {
    kit_slot_id: number;
    product_id: number;
    selected: boolean;
}

export interface Armado {
    id: number;
    /** The customer the armado is for; the sale's customer only pays. */
    patient_id: number | null;
    /** Display label of the patient, for the cart row only. */
    patient_name: string;
    prescription_id: number | null;
    lens: ArmadoLensLine | null;
    frame: ArmadoLine | null;
    own_frame: boolean;
    slots: ArmadoSlot[];
}

export interface LooseProduct {
    product_id: number | null;
    description: string;
    quantity: number;
    unit_price: number;
}

function discounted(price: number, percent: number): number {
    return Math.round((price * (100 - percent)) / 100);
}

/** Mirrors KitSlot::unitPriceFor(): what one unit of the slot product costs. */
export function slotUnitPrice(slot: KitSlotProp, price: number): number {
    switch (slot.price_mode) {
        case 'normal':
            return price;
        case 'discount_percent':
            return discounted(price, slot.price_value);
        default:
            return 0;
    }
}

/** The selected slots of an armado, resolved against the kit (unknown slots/products are dropped). */
export function selectedSlotLines(
    armado: Armado,
    kit: KitProp,
): { slot: KitSlotProp; product: KitSlotProp['products'][number] }[] {
    return armado.slots.flatMap((selection) => {
        const slot = kit.armado_slots.find(
            (s) => s.id === selection.kit_slot_id,
        );
        const product = slot?.products.find(
            (p) => p.id === selection.product_id,
        );

        return selection.selected && slot && product ? [{ slot, product }] : [];
    });
}

/** The lens line price as RegisterSale charges it: override or catalog price, plus `added_to_lens` surcharges. */
export function armadoLensPrice(armado: Armado, kit: KitProp): number {
    if (!armado.lens) {
        return 0;
    }

    const surcharges = selectedSlotLines(armado, kit)
        .filter(({ slot }) => slot.price_mode === 'added_to_lens')
        .reduce((sum, { slot }) => sum + Math.trunc(slot.price_value), 0);

    return (armado.lens.price_override ?? armado.lens.price) + surcharges;
}

/** Mirrors Company::armadoFrameUnitPrice(). */
export function armadoFramePrice(armado: Armado, kit: KitProp): number {
    const price = !armado.own_frame ? (armado.frame?.unit_price ?? 0) : 0;

    switch (kit.frame_price_mode) {
        case 'included':
            return 0;
        case 'discount_percent':
            return discounted(price, kit.frame_discount_percent);
        default:
            return price;
    }
}

/** The armado's share of the sale total; must equal what RegisterSale charges. */
export function armadoTotal(armado: Armado, kit: KitProp): number {
    const slots = selectedSlotLines(armado, kit).reduce(
        (sum, { slot, product }) =>
            sum + slotUnitPrice(slot, product.price) * slot.quantity,
        0,
    );

    return armadoLensPrice(armado, kit) + armadoFramePrice(armado, kit) + slots;
}

export function usePosCart(kit: KitProp) {
    const armados: Ref<Armado[]> = ref([]);
    const products: Ref<LooseProduct[]> = ref([]);
    const discountPercent = ref(0);
    const surchargePercent = ref(0);
    let nextId = 1;

    function commitArmado(data: Omit<Armado, 'id'>): Armado {
        const armado: Armado = { id: nextId++, ...data };
        armados.value.push(armado);

        return armado;
    }

    function updateArmado(id: number, data: Omit<Armado, 'id'>): void {
        armados.value = armados.value.map((a) =>
            a.id === id ? { id, ...data } : a,
        );
    }

    function removeArmado(id: number): void {
        armados.value = armados.value.filter((a) => a.id !== id);
    }

    function addProduct(): void {
        products.value.push({
            product_id: null,
            description: '',
            quantity: 1,
            unit_price: 0,
        });
    }

    function addOrIncrementProduct(product: {
        id: number;
        name: string;
        price: number;
    }): void {
        const existing = products.value.find(
            (p) => p.product_id === product.id,
        );

        if (existing) {
            existing.quantity += 1;

            return;
        }

        products.value.push({
            product_id: product.id,
            description: product.name,
            quantity: 1,
            unit_price: product.price,
        });
    }

    function removeProduct(index: number): void {
        products.value.splice(index, 1);
    }

    const subtotal: ComputedRef<number> = computed(() => {
        const armadoSum = armados.value.reduce(
            (sum, a) => sum + armadoTotal(a, kit),
            0,
        );
        const productSum = products.value.reduce(
            (sum, p) => sum + p.quantity * p.unit_price,
            0,
        );

        return armadoSum + productSum;
    });

    const discountAmount: ComputedRef<number> = computed(() =>
        Math.round((subtotal.value * (discountPercent.value || 0)) / 100),
    );

    const base: ComputedRef<number> = computed(() =>
        Math.max(0, subtotal.value - discountAmount.value),
    );

    // Prices are tax-inclusive, so tax never adds to the total.
    const total: ComputedRef<number> = computed(() =>
        Math.round(base.value * (1 + (surchargePercent.value || 0) / 100)),
    );

    function buildPayload(): { armados: unknown[]; products: unknown[] } {
        return {
            armados: armados.value
                .filter((a) => a.lens !== null)
                .map((a) => ({
                    patient_id: a.patient_id,
                    prescription_id: a.prescription_id,
                    // `price`/`cost` stay client-side: the backend resolves
                    // the real amounts from the lens catalog itself.
                    lens: {
                        description: a.lens!.description,
                        quantity: a.lens!.quantity,
                        price_override: a.lens!.price_override,
                        lens_type_id: a.lens!.lens_type_id,
                        lens_technology_id: a.lens!.lens_technology_id,
                        lens_material_id: a.lens!.lens_material_id,
                        treatment_ids: a.lens!.treatment_ids,
                    },
                    frame: a.own_frame ? null : a.frame,
                    own_frame: a.own_frame,
                    slots: a.slots,
                })),
            products: products.value.filter((p) => p.description !== ''),
        };
    }

    return {
        armados,
        products,
        discountPercent,
        surchargePercent,
        subtotal,
        discountAmount,
        total,
        commitArmado,
        updateArmado,
        removeArmado,
        addProduct,
        addOrIncrementProduct,
        removeProduct,
        buildPayload,
    };
}
