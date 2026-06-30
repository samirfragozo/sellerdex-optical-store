import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export interface LensSpecs {
    design: string;
    process: string;
    material: string;
    filter: string;
}

export interface ProductProp {
    id: number;
    name: string;
    price: number;
    category_key: string | null;
    specs: Record<string, string> | null;
}

export interface LensProduct {
    id: number;
    name: string;
    price: number;
    specs: LensSpecs;
}

function isLens(p: ProductProp): boolean {
    return p.category_key === 'lens' && !!p.specs?.design;
}

export function useLensCatalog(products: Ref<ProductProp[]>) {
    const lenses = computed<LensProduct[]>(() =>
        products.value.filter(isLens).map((p) => ({
            id: p.id,
            name: p.name,
            price: p.price,
            specs: p.specs as unknown as LensSpecs,
        })),
    );

    const uniq = (values: string[]): string[] => [...new Set(values)];

    const designs = computed(() => uniq(lenses.value.map((l) => l.specs.design)));

    const processesFor = (design: string): string[] =>
        uniq(lenses.value.filter((l) => l.specs.design === design).map((l) => l.specs.process));

    const materialsFor = (design: string, process: string): string[] =>
        uniq(
            lenses.value
                .filter((l) => l.specs.design === design && l.specs.process === process)
                .map((l) => l.specs.material),
        );

    const filtersFor = (design: string, process: string, material: string): string[] =>
        uniq(
            lenses.value
                .filter(
                    (l) =>
                        l.specs.design === design &&
                        l.specs.process === process &&
                        l.specs.material === material,
                )
                .map((l) => l.specs.filter),
        );

    const resolve = (sel: LensSpecs): LensProduct | null =>
        lenses.value.find(
            (l) =>
                l.specs.design === sel.design &&
                l.specs.process === sel.process &&
                l.specs.material === sel.material &&
                l.specs.filter === sel.filter,
        ) ?? null;

    return { designs, processesFor, materialsFor, filtersFor, resolve } as {
        designs: ComputedRef<string[]>;
        processesFor: (design: string) => string[];
        materialsFor: (design: string, process: string) => string[];
        filtersFor: (design: string, process: string, material: string) => string[];
        resolve: (sel: LensSpecs) => LensProduct | null;
    };
}
