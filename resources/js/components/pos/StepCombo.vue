<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { ArmadoSlot, KitSlotProp } from '@/composables/usePosCart';
import { slotUnitPrice } from '@/composables/usePosCart';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    kitSlots: KitSlotProp[];
}>();

const slots = defineModel<ArmadoSlot[]>('slots', { required: true });
const { trans } = useTranslations();

const formatCOP = (value: number): string =>
    '$' + new Intl.NumberFormat('es-CO').format(value);

// Each armado slot selection paired with its kit slot definition.
const rows = computed(() =>
    slots.value.flatMap((selection) => {
        const slot = props.kitSlots.find((s) => s.id === selection.kit_slot_id);

        return slot ? [{ selection, slot }] : [];
    }),
);

function priceText(slot: KitSlotProp, selection: ArmadoSlot): string {
    if (slot.price_mode === 'free') {
        return trans('app.pos.kit.gift');
    }

    if (slot.price_mode === 'added_to_lens') {
        return trans('app.pos.kit.added_to_lens').replace(
            ':amount',
            formatCOP(Math.trunc(slot.price_value)),
        );
    }

    const price =
        slot.products.find((p) => p.id === selection.product_id)?.price ?? 0;

    return formatCOP(slotUnitPrice(slot, price) * slot.quantity);
}
</script>

<template>
    <div class="flex flex-col divide-y divide-border">
        <div
            v-for="{ selection, slot } in rows"
            :key="selection.kit_slot_id"
            class="flex flex-wrap items-center gap-3 py-3"
        >
            <div class="flex min-w-40 flex-1 items-center gap-2">
                <Checkbox
                    :id="`kit_slot_${selection.kit_slot_id}_selected`"
                    v-model="selection.selected"
                    :disabled="!slot.is_optional"
                />
                <Label :for="`kit_slot_${selection.kit_slot_id}_selected`">
                    {{ slot.category_name }}
                    <span v-if="slot.quantity > 1" class="text-muted-foreground"
                        >x{{ slot.quantity }}</span
                    >
                </Label>
                <span
                    v-if="!slot.is_optional"
                    class="text-xs text-muted-foreground"
                >
                    {{ trans('app.pos.kit.required') }}
                </span>
            </div>

            <select
                v-if="slot.products.length > 1"
                :id="`kit_slot_${selection.kit_slot_id}`"
                v-model.number="selection.product_id"
                :aria-label="slot.category_name"
                :disabled="!selection.selected"
                class="h-9 min-w-40 flex-1 rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
            >
                <option
                    v-for="product in slot.products"
                    :key="product.id"
                    :value="product.id"
                >
                    {{ product.name }}
                </option>
            </select>
            <span v-else class="min-w-40 flex-1 text-sm">
                {{ slot.products[0]?.name }}
            </span>

            <span
                class="w-32 text-right text-sm text-muted-foreground tabular-nums"
            >
                {{ priceText(slot, selection) }}
            </span>
        </div>
    </div>
</template>
