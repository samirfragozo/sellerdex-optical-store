<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

interface Customer {
    id: number;
    name: string;
    last_name: string;
    id_number: string | null;
}

interface NewCustomer {
    name: string;
    last_name: string;
    document_type: string;
    id_number: string;
    phone: string;
    address: string;
    city: string;
    birth_date: string;
    email: string;
    notes: string;
}

interface CustomerErrors {
    customer_id?: string;
    'customer.name'?: string;
    'customer.last_name'?: string;
    'customer.document_type'?: string;
    'customer.id_number'?: string;
    'customer.phone'?: string;
    'customer.email'?: string;
    'customer.birth_date'?: string;
    'customer.address'?: string;
    'customer.city'?: string;
    'customer.notes'?: string;
}

const props = defineProps<{
    customers: Customer[];
    errors?: CustomerErrors;
    today?: string;
}>();

const customerMode = defineModel<'none' | 'existing' | 'new'>('customerMode', {
    required: true,
});
const customerId = defineModel<number | null>('customerId', { required: true });
const customer = defineModel<NewCustomer>('customer', { required: true });
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
                :class="[
                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                    customerMode === 'new'
                        ? 'bg-primary text-primary-foreground'
                        : 'border border-input bg-transparent hover:bg-accent',
                ]"
                @click="customerMode = 'new'"
            >
                {{ trans('app.pos.new_customer') }}
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
        <p
            v-else-if="customerMode === 'none'"
            class="text-sm text-muted-foreground"
        >
            {{ trans('app.pos.customer_form.no_customer_notice') }}
        </p>

        <!-- New customer fields -->
        <div v-else class="grid gap-3 sm:grid-cols-2">
            <div>
                <Label for="customer_name">
                    {{ trans('app.fields.name') }}
                    <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="customer_name"
                    v-model="customer.name"
                    class="mt-1 w-full"
                    :placeholder="trans('app.fields.name')"
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.name']"
                />
            </div>
            <div>
                <Label for="customer_last_name">
                    {{ trans('app.fields.last_name') }}
                    <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="customer_last_name"
                    v-model="customer.last_name"
                    class="mt-1 w-full"
                    :placeholder="trans('app.fields.last_name')"
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.last_name']"
                />
            </div>
            <div>
                <Label for="customer_document_type">
                    {{ trans('app.fields.document_type') }}
                    <span class="text-destructive">*</span>
                </Label>
                <select
                    id="customer_document_type"
                    v-model="customer.document_type"
                    class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                >
                    <option value="">
                        {{ trans('app.pos.select_option') }}
                    </option>
                    <option value="cc">
                        {{ trans('app.document_type.cc') }}
                    </option>
                    <option value="ce">
                        {{ trans('app.document_type.ce') }}
                    </option>
                    <option value="ti">
                        {{ trans('app.document_type.ti') }}
                    </option>
                    <option value="nit">
                        {{ trans('app.document_type.nit') }}
                    </option>
                    <option value="pa">
                        {{ trans('app.document_type.pa') }}
                    </option>
                </select>
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.document_type']"
                />
            </div>
            <div>
                <Label for="customer_id_number">
                    {{ trans('app.fields.id_number') }}
                    <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="customer_id_number"
                    v-model="customer.id_number"
                    class="mt-1 w-full"
                    :placeholder="
                        trans('app.pos.customer_form.id_number_placeholder')
                    "
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.id_number']"
                />
            </div>
            <div>
                <Label for="customer_phone">
                    {{ trans('app.fields.phone') }}
                    <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="customer_phone"
                    v-model="customer.phone"
                    class="mt-1 w-full"
                    :placeholder="
                        trans('app.pos.customer_form.phone_placeholder')
                    "
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.phone']"
                />
            </div>
            <div>
                <Label for="customer_email">{{
                    trans('app.fields.email')
                }}</Label>
                <Input
                    id="customer_email"
                    v-model="customer.email"
                    type="email"
                    class="mt-1 w-full"
                    :placeholder="
                        trans('app.pos.customer_form.email_placeholder')
                    "
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.email']"
                />
            </div>
            <div>
                <Label for="customer_birth_date">{{
                    trans('app.fields.birth_date')
                }}</Label>
                <Input
                    id="customer_birth_date"
                    v-model="customer.birth_date"
                    type="date"
                    :max="props.today"
                    class="mt-1 w-full"
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.birth_date']"
                />
            </div>
            <div>
                <Label for="customer_address">{{
                    trans('app.fields.address')
                }}</Label>
                <Input
                    id="customer_address"
                    v-model="customer.address"
                    class="mt-1 w-full"
                    :placeholder="
                        trans('app.pos.customer_form.address_placeholder')
                    "
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.address']"
                />
            </div>
            <div>
                <Label for="customer_city">{{
                    trans('app.fields.city')
                }}</Label>
                <Input
                    id="customer_city"
                    v-model="customer.city"
                    class="mt-1 w-full"
                    :placeholder="trans('app.fields.city')"
                />
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.city']"
                />
            </div>
            <div class="sm:col-span-2">
                <Label for="customer_notes">{{
                    trans('app.fields.notes')
                }}</Label>
                <textarea
                    id="customer_notes"
                    v-model="customer.notes"
                    rows="2"
                    class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                    :placeholder="
                        trans('app.pos.customer_form.notes_placeholder')
                    "
                ></textarea>
                <InputError
                    class="mt-1"
                    :message="props.errors?.['customer.notes']"
                />
            </div>
        </div>
    </div>
</template>
