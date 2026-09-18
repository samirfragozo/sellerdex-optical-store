<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import CreateCustomerModal from '@/components/pos/CreateCustomerModal.vue';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import type { CreatedCustomer } from '@/composables/useCreateCustomer';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { search as searchCustomers } from '@/routes/pos/customers';

const { trans } = useTranslations();

interface Customer {
    id: number;
    name: string;
    last_name: string;
    id_number: string | null;
}

const props = withDefaults(
    defineProps<{
        errors?: { customer_id?: string };
        today?: string;
        optional?: boolean;
    }>(),
    { optional: true },
);

const customerId = defineModel<number | null>('customerId', { required: true });

const selectedCustomer = ref<Customer | null>(null);
const results = ref<Customer[]>([]);
const isSearching = ref(false);
const showCreateModal = ref(false);

const customerLabel = (customer: Customer): string =>
    `${customer.name} ${customer.last_name}`.trim() +
    (customer.id_number ? ` — ${customer.id_number}` : '');

const items = computed<ComboboxOption[]>(() =>
    results.value.map((c) => ({ value: c.id, label: customerLabel(c) })),
);

async function runSearch(query: string): Promise<void> {
    if (query.trim() === '') {
        results.value = [];

        return;
    }

    isSearching.value = true;

    try {
        const response = await csrfFetch(
            searchCustomers.url({ query: { q: query } }),
        );
        results.value = response.ok
            ? ((await response.json()) as Customer[])
            : [];
    } finally {
        isSearching.value = false;
    }
}

const debouncedSearch = useDebounceFn(runSearch, 400);

function onSelect(id: number | null): void {
    customerId.value = id;
    selectedCustomer.value =
        id === null
            ? null
            : (results.value.find((c) => c.id === id) ??
              selectedCustomer.value);
}

// The parent resets customerId to null after a sale completes — clear the
// stale label/results so the field visually resets too, instead of still
// showing the previous customer's name.
watch(customerId, (id) => {
    if (id === null) {
        selectedCustomer.value = null;
        results.value = [];
    }
});

function onCustomerCreated(customer: CreatedCustomer): void {
    customerId.value = customer.id;
    selectedCustomer.value = customer;
    results.value = [customer];
    showCreateModal.value = false;
}
</script>

<template>
    <div>
        <Label for="customer_id" class="sr-only">{{
            trans('app.pos.customer_form.select_customer')
        }}</Label>
        <div class="flex items-center">
            <Combobox
                id="customer_id"
                class="flex-1 rounded-r-none"
                :items="items"
                :model-value="customerId"
                :model-label="
                    selectedCustomer ? customerLabel(selectedCustomer) : ''
                "
                :loading="isSearching"
                :placeholder="trans('app.pos.customer_form.search_placeholder')"
                :empty-text="trans('app.pos.customer_form.no_results')"
                :clear-text="trans('app.pos.customer_form.clear_customer')"
                @update:model-value="onSelect"
                @search="debouncedSearch"
            />
            <Button
                type="button"
                size="icon"
                class="-ml-px rounded-l-none"
                :aria-label="trans('app.pos.customer_form.new_customer_title')"
                @click="showCreateModal = true"
            >
                <Plus class="size-4" />
            </Button>
        </div>
        <InputError class="mt-1" :message="props.errors?.customer_id" />
        <p
            v-if="customerId === null && props.optional"
            class="mt-1 text-sm text-muted-foreground"
        >
            {{ trans('app.pos.customer_form.no_customer_notice') }}
        </p>

        <CreateCustomerModal
            v-model:open="showCreateModal"
            :today="props.today"
            @created="onCustomerCreated"
        />
    </div>
</template>
