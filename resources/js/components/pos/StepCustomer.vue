<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CreateCustomerModal from '@/components/pos/CreateCustomerModal.vue';
import { Label } from '@/components/ui/label';
import type { CreatedCustomer } from '@/composables/useCreateCustomer';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

interface Customer {
    id: number;
    name: string;
    last_name: string;
    id_number: string | null;
}

const props = defineProps<{
    customers: Customer[];
    errors?: { customer_id?: string };
    today?: string;
}>();

const emit = defineEmits<{ 'customer-created': [CreatedCustomer] }>();

const customerMode = defineModel<'none' | 'existing'>('customerMode', {
    required: true,
});
const customerId = defineModel<number | null>('customerId', { required: true });

const showCreateModal = ref(false);

function onCustomerCreated(customer: CreatedCustomer): void {
    emit('customer-created', customer);
    customerMode.value = 'existing';
    customerId.value = customer.id;
}
</script>

<template>
    <div>
        <!-- Toggle buttons -->
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                :class="[
                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                    customerMode === 'none'
                        ? 'bg-primary text-primary-foreground'
                        : 'border border-input bg-transparent hover:bg-accent',
                ]"
                @click="customerMode = 'none'"
            >
                {{ trans('app.pos.no_customer') }}
            </button>
            <button
                type="button"
                :class="[
                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                    customerMode === 'existing'
                        ? 'bg-primary text-primary-foreground'
                        : 'border border-input bg-transparent hover:bg-accent',
                ]"
                @click="customerMode = 'existing'"
            >
                {{ trans('app.pos.existing_customer') }}
            </button>
            <button
                type="button"
                class="rounded-md border border-input bg-transparent px-3 py-1.5 text-sm font-medium transition-colors hover:bg-accent"
                @click="showCreateModal = true"
            >
                + {{ trans('app.pos.new_customer') }}
            </button>
        </div>

        <!-- Existing customer select -->
        <div v-if="customerMode === 'existing'">
            <Label for="customer_id">{{
                trans('app.pos.customer_form.select_customer')
            }}</Label>
            <select
                id="customer_id"
                v-model="customerId"
                class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
            >
                <option :value="null">
                    {{ trans('app.pos.customer_form.no_customer_option') }}
                </option>
                <option v-for="c in customers" :key="c.id" :value="c.id">
                    {{ c.name }} {{ c.last_name }}
                    <template v-if="c.id_number"> — {{ c.id_number }}</template>
                </option>
            </select>
            <InputError class="mt-1" :message="props.errors?.customer_id" />
        </div>

        <!-- No customer -->
        <p v-else class="text-sm text-muted-foreground">
            {{ trans('app.pos.customer_form.no_customer_notice') }}
        </p>

        <CreateCustomerModal
            v-model:open="showCreateModal"
            :today="props.today"
            @created="onCustomerCreated"
        />
    </div>
</template>
