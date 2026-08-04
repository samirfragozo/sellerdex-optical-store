<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Armado, LooseProduct } from '@/composables/usePosCart';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    armados: Armado[];
    products: LooseProduct[];
    subtotal: number;
    total: number;
    surchargePercent: number;
    balance: number;
    formatCOP: (value: number) => string;
    discountError?: string;
}>();

const discount = defineModel<number>('discount', { required: true });

const openArmados = ref<Set<number>>(new Set());

function toggleArmado(id: number): void {
    if (openArmados.value.has(id)) {
        openArmados.value.delete(id);
    } else {
        openArmados.value.add(id);
    }

    openArmados.value = new Set(openArmados.value);
}
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <h2 class="mb-3 text-base font-semibold">
            {{ trans('app.pos.summary.title') }}
        </h2>

        <!-- Armados (lens + frame combos) -->
        <div v-if="armados.length > 0" class="mb-3 flex flex-col gap-2">
            <div
                v-for="armado in armados"
                :key="armado.id"
                class="rounded-md border border-border"
            >
                <!-- Armado header (collapsible) -->
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm font-medium"
                    @click="toggleArmado(armado.id)"
                >
                    <span>
                        {{
                            trans('app.pos.summary.armado').replace(
                                ':id',
                                String(armado.id),
                            )
                        }}
                        <span
                            v-if="armado.lens"
                            class="ml-1 text-xs font-normal text-muted-foreground"
                        >
                            — {{ armado.lens.description }}
                        </span>
                    </span>
                    <ChevronDown
                        class="size-4 transition-transform"
                        :class="openArmados.has(armado.id) ? 'rotate-180' : ''"
                    />
                </button>

                <!-- Armado detail -->
                <div
                    v-show="openArmados.has(armado.id)"
                    class="border-t border-border px-3 py-2"
                >
                    <div class="flex flex-col gap-1 text-sm">
                        <div v-if="armado.lens" class="flex justify-between">
                            <span class="text-muted-foreground">{{
                                trans('app.pos.summary.lens')
                            }}</span>
                            <span class="tabular-nums">{{
                                props.formatCOP(armado.lens.unit_price)
                            }}</span>
                        </div>
                        <div
                            v-if="armado.frame && !armado.own_frame"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">{{
                                trans('app.pos.summary.frame')
                            }}</span>
                            <span class="tabular-nums">{{
                                props.formatCOP(armado.frame.unit_price)
                            }}</span>
                        </div>
                        <div
                            v-if="armado.own_frame"
                            class="text-xs text-muted-foreground"
                        >
                            {{ trans('app.pos.summary.own_frame') }}
                        </div>
                        <div
                            v-if="armado.combo.with_exam"
                            class="text-xs text-muted-foreground"
                        >
                            {{ trans('app.pos.summary.exam_included') }}
                        </div>
                        <div
                            v-if="armado.combo.include_liquid"
                            class="text-xs text-muted-foreground"
                        >
                            {{ trans('app.pos.summary.liquid_included') }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ trans('app.pos.summary.lining') }}:
                            {{
                                armado.combo.forro === 'small'
                                    ? trans('app.pos.summary.small')
                                    : trans('app.pos.summary.large')
                            }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loose products -->
        <div v-if="products.length > 0" class="mb-3 flex flex-col gap-1">
            <div
                v-for="(product, idx) in products"
                :key="idx"
                class="flex justify-between text-sm"
            >
                <span class="text-muted-foreground">
                    {{
                        product.description ||
                        trans('app.pos.summary.no_description')
                    }}
                    <span v-if="product.quantity > 1"
                        >x{{ product.quantity }}</span
                    >
                </span>
                <span class="tabular-nums">{{
                    props.formatCOP(product.quantity * product.unit_price)
                }}</span>
            </div>
        </div>

        <!-- Totals block -->
        <div class="flex flex-col gap-2 text-sm">
            <div class="flex justify-between">
                <span class="text-muted-foreground">{{
                    trans('app.fields.subtotal')
                }}</span>
                <span class="font-medium tabular-nums">{{
                    props.formatCOP(subtotal)
                }}</span>
            </div>

            <div class="flex items-center justify-between gap-2">
                <Label for="discount" class="text-muted-foreground">{{
                    trans('app.fields.discount')
                }}</Label>
                <Input
                    id="discount"
                    v-model.number="discount"
                    type="number"
                    min="0"
                    class="w-28 text-right"
                    placeholder="0"
                />
            </div>
            <InputError :message="props.discountError" />

            <!-- Surcharge note -->
            <div
                v-if="surchargePercent > 0"
                class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
            >
                {{
                    trans('app.pos.summary.surcharge_note').replace(
                        ':percent',
                        String(surchargePercent),
                    )
                }}
            </div>

            <div
                class="flex justify-between border-t border-border pt-2 text-base font-semibold"
            >
                <span>{{ trans('app.fields.total') }}</span>
                <span class="tabular-nums">{{ props.formatCOP(total) }}</span>
            </div>

            <!-- Balance (only shown when non-zero or relevant) -->
            <div
                v-if="balance !== total"
                class="flex justify-between border-t border-border pt-2 text-sm"
            >
                <span class="text-muted-foreground">{{
                    trans('app.pos.summary.balance')
                }}</span>
                <span
                    :class="[
                        'font-semibold tabular-nums',
                        balance < 0 ? 'text-destructive' : '',
                    ]"
                    >{{ props.formatCOP(balance) }}</span
                >
            </div>
        </div>
    </div>
</template>
