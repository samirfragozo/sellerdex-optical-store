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
    category_key: string | null;
    category_name: string | null;
    is_stockable: boolean;
    stock: number | null;
    specs: Record<string, string> | null;
    option_groups: OptionGroupProp[];
    variants: VariantProp[];
}

export interface LensProduct {
    id: number;
    name: string;
    price: number;
    cost: number;
    specs: LensSpecs;
    option_ids?: number[];
}

