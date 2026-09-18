export interface LensSpecs {
    design: string;
    process: string;
    material: string;
    filter: string;
}

export interface OptionProp {
    id: number;
    name: string;
    price: number;
    cost: number;
}

export interface OptionGroupProp {
    id: number;
    name: string;
    is_required: boolean;
    options: OptionProp[];
}

export interface VariantProp {
    id: number;
    price: number;
    cost: number;
    stock: number | null;
    is_stockable: boolean;
    option_ids: number[];
}

export interface ProductProp {
    id: number;
    name: string;
    price: number;
    cost: number;
    tax_rate: number;
    category_key: string | null;
    category_name: string | null;
    is_stockable: boolean;
    stock: number | null;
    specs: Record<string, string> | null;
    option_groups: OptionGroupProp[];
    variants: VariantProp[];
}

export interface PaginatedProducts {
    data: ProductProp[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
}

export interface LensProduct {
    id: number;
    name: string;
    price: number;
    cost: number;
    specs: LensSpecs;
    option_ids?: number[];
}

export interface VariantOption {
    value: number;
    label: string;
    unit_price: number;
    unit_cost?: number;
}

/**
 * Flattens a product into the real, sellable combinations it represents:
 * itself when it has no option groups, or each of its variants resolved to
 * a "name — option, option" label when it does (a base product's own price
 * is a placeholder, never sellable on its own).
 */
export function resolveVariantOptions(product: ProductProp): VariantOption[] {
    if (product.option_groups.length === 0) {
        return [
            {
                value: product.id,
                label: product.name,
                unit_price: product.price,
                unit_cost: product.cost,
            },
        ];
    }

    const allOptions = product.option_groups.flatMap((group) => group.options);

    return product.variants.map((variant) => {
        const names = variant.option_ids
            .map((id) => allOptions.find((o) => o.id === id)?.name)
            .filter((name): name is string => !!name);

        return {
            value: variant.id,
            label: [product.name, ...names].join(' — '),
            unit_price: variant.price,
            unit_cost: variant.cost,
        };
    });
}

