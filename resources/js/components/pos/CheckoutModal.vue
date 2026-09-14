<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { usePosCheckout } from '@/composables/usePosCheckout';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

defineProps<{
    open: boolean;
    paymentMethods: { id: number; name: string; surcharge_percent: number }[];
    documentTypes: { value: string; label: string }[];
    total: number;
    checkout: ReturnType<typeof usePosCheckout>;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    confirm: [];
}>();

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ trans('app.pos.checkout.title') }}</DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-4">
                <!-- Document type -->
                <div>
                    <Label>{{
                        trans('app.pos.payment_form.document_type')
                    }}</Label>
                    <div class="mt-1 flex flex-col gap-1">
                        <label
                            v-for="dt in documentTypes"
                            :key="dt.value"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-accent"
                        >
                            <!-- eslint-disable vue/no-mutating-props -->
                            <input
                                v-model="checkout.documentType.value"
                                type="radio"
                                :value="dt.value"
                                class="size-4 accent-primary"
                            />
                            <!-- eslint-enable vue/no-mutating-props -->
                            {{ dt.label }}
                        </label>
                    </div>
                </div>

                <!-- Split payments -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <Label>{{ trans('app.pos.checkout.payments') }}</Label>
                        <span class="text-sm text-muted-foreground">
                            {{ trans('app.pos.checkout.remaining') }}:
                            {{ formatCOP(checkout.remaining.value) }}
                        </span>
                    </div>

                    <div
                        v-for="(payment, index) in checkout.payments.value"
                        :key="index"
                        class="mb-2 grid grid-cols-[1fr_7rem_2rem] items-center gap-2"
                    >
                        <select
                            v-model="payment.payment_method_id"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option :value="null">
                                {{ trans('app.pos.select_option') }}
                            </option>
                            <option
                                v-for="pm in paymentMethods"
                                :key="pm.id"
                                :value="pm.id"
                            >
                                {{ pm.name }}
                            </option>
                        </select>
                        <Input
                            v-model.number="payment.amount"
                            type="number"
                            min="0"
                            class="text-right"
                        />
                        <button
                            type="button"
                            class="flex size-8 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring/50"
                            @click="checkout.removePayment(index)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="gap-2"
                        @click="checkout.addPayment()"
                    >
                        <Plus class="size-4" />
                        {{ trans('app.pos.checkout.add_payment') }}
                    </Button>
                </div>

                <!-- Notes -->
                <div>
                    <Label for="checkout_notes">{{
                        trans('app.fields.notes')
                    }}</Label>
                    <!-- eslint-disable vue/no-mutating-props -->
                    <textarea
                        id="checkout_notes"
                        v-model="checkout.notes.value"
                        rows="2"
                        class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        :placeholder="trans('app.pos.notes_placeholder')"
                    ></textarea>
                    <!-- eslint-enable vue/no-mutating-props -->
                </div>

                <InputError :message="checkout.errors.value.cash_session" />
                <div
                    v-if="Object.keys(checkout.errors.value).length > 0"
                    class="rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    <p class="mb-1 font-medium">
                        {{ trans('app.pos.fix_errors') }}
                    </p>
                    <ul class="list-disc space-y-0.5 pl-5">
                        <li
                            v-for="(message, key) in checkout.errors.value"
                            :key="key"
                        >
                            {{ message }}
                        </li>
                    </ul>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    :disabled="checkout.submitting.value"
                    @click="emit('confirm')"
                >
                    {{
                        checkout.submitting.value
                            ? trans('app.pos.saving')
                            : trans('app.pos.checkout.confirm')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
