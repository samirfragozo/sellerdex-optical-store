import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export interface ArmadoLine {
    product_id: number;
    description: string;
    unit_price: number;
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
}

export function usePosCart() {
    const armados: Ref<Armado[]> = ref([]);
    const products: Ref<LooseProduct[]> = ref([]);
    const discount = ref(0);
    const surchargePercent = ref(0);
    let nextId = 1;

    function addArmado(): Armado {
        const armado: Armado = {
            id: nextId++,
            lens: null,
            frame: null,
            own_frame: false,
            combo: { with_exam: false, forro: 'small', include_liquid: true },
        };
        armados.value.push(armado);

        return armado;
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

    function removeProduct(index: number): void {
        products.value.splice(index, 1);
    }

    const subtotal: ComputedRef<number> = computed(() => {
        const armadoSum = armados.value.reduce((sum, a) => {
            const lens = a.lens?.unit_price ?? 0;
            const frame = !a.own_frame ? (a.frame?.unit_price ?? 0) : 0;

            return sum + lens + frame;
        }, 0);
        const productSum = products.value.reduce(
            (sum, p) => sum + p.quantity * p.unit_price,
            0,
        );

        return armadoSum + productSum;
    });

    const total: ComputedRef<number> = computed(() => {
        const base = Math.max(0, subtotal.value - (discount.value || 0));

        return Math.round(base * (1 + (surchargePercent.value || 0) / 100));
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
        discount,
        surchargePercent,
        subtotal,
        total,
        addArmado,
        removeArmado,
        addProduct,
        removeProduct,
        buildPayload,
    };
}
