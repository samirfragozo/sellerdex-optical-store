import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export interface ArmadoLine {
    product_id: number;
    description: string;
    unit_price: number;
    unit_cost?: number;
    option_ids?: number[];
}

export interface Armado {
    id: number;
    lens: ArmadoLine | null;
    frame: ArmadoLine | null;
    own_frame: boolean;
    combo: {
        with_exam: boolean;
        forro: 'small' | 'large';
        include_liquid: boolean;
    };
}

export interface LooseProduct {
    product_id: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    tax_rate?: number;
}

export function armadoTotal(armado: Armado): number {
    const lens = armado.lens?.unit_price ?? 0;
    const frame = !armado.own_frame ? (armado.frame?.unit_price ?? 0) : 0;

    return lens + frame;
}

export function usePosCart() {
    const armados: Ref<Armado[]> = ref([]);
    const products: Ref<LooseProduct[]> = ref([]);
    const discountPercent = ref(0);
    const tipPercent = ref(0);
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
        tax_rate?: number;
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
            tax_rate: product.tax_rate ?? 0,
        });
    }

    function removeProduct(index: number): void {
        products.value.splice(index, 1);
    }

    const subtotal: ComputedRef<number> = computed(() => {
        const armadoSum = armados.value.reduce(
            (sum, a) => sum + armadoTotal(a),
            0,
        );
        const productSum = products.value.reduce(
            (sum, p) => sum + p.quantity * p.unit_price,
            0,
        );

        return armadoSum + productSum;
    });

    const rawTax: ComputedRef<number> = computed(() =>
        products.value.reduce(
            (sum, p) =>
                sum +
                Math.round(
                    (p.quantity * p.unit_price * (p.tax_rate ?? 0)) / 100,
                ),
            0,
        ),
    );

    const discountAmount: ComputedRef<number> = computed(() =>
        Math.round((subtotal.value * (discountPercent.value || 0)) / 100),
    );

    const base: ComputedRef<number> = computed(() =>
        Math.max(0, subtotal.value - discountAmount.value),
    );

    const taxAmount: ComputedRef<number> = computed(() =>
        subtotal.value > 0
            ? Math.round(rawTax.value * (base.value / subtotal.value))
            : 0,
    );

    const tipAmount: ComputedRef<number> = computed(() =>
        Math.round((base.value * (tipPercent.value || 0)) / 100),
    );

    const total: ComputedRef<number> = computed(() => {
        const preSurcharge = base.value + taxAmount.value + tipAmount.value;

        return Math.round(
            preSurcharge * (1 + (surchargePercent.value || 0) / 100),
        );
    });

    function buildPayload(): { armados: unknown[]; products: unknown[] } {
        return {
            armados: armados.value
                .filter((a) => a.lens !== null)
                .map((a) => ({
                    lens: a.lens,
                    frame: a.own_frame ? null : a.frame,
                    own_frame: a.own_frame,
                    combo: a.combo,
                })),
            products: products.value.filter((p) => p.description !== ''),
        };
    }

    return {
        armados,
        products,
        discountPercent,
        tipPercent,
        surchargePercent,
        subtotal,
        discountAmount,
        taxAmount,
        tipAmount,
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
