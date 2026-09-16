<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CreatedCustomer } from '@/composables/useCreateCustomer';
import { useCreateCustomer } from '@/composables/useCreateCustomer';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{
    open: boolean;
    today?: string;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    created: [CreatedCustomer];
}>();

const { errors, submitting, submit } = useCreateCustomer();

function emptyForm() {
    return {
        name: '',
        last_name: '',
        document_type: 'cc',
        id_number: '',
        phone: '',
        address: '',
        city: '',
        birth_date: '',
        email: '',
        notes: '',
    };
}

const form = ref(emptyForm());

function onOpenChange(value: boolean): void {
    if (!value) {
        form.value = emptyForm();
        errors.value = {};
    }

    emit('update:open', value);
}

async function save(): Promise<void> {
    const customer = await submit(form.value);

    if (customer) {
        emit('created', customer);
        onOpenChange(false);
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="onOpenChange">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    trans('app.pos.customer_form.new_customer_title')
                }}</DialogTitle>
            </DialogHeader>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <Label for="new_customer_name">
                        {{ trans('app.fields.name') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="new_customer_name"
                        v-model="form.name"
                        class="mt-1 w-full"
                        :placeholder="trans('app.fields.name')"
                    />
                    <InputError class="mt-1" :message="errors.name" />
                </div>
                <div>
                    <Label for="new_customer_last_name">
                        {{ trans('app.fields.last_name') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="new_customer_last_name"
                        v-model="form.last_name"
                        class="mt-1 w-full"
                        :placeholder="trans('app.fields.last_name')"
                    />
                    <InputError class="mt-1" :message="errors.last_name" />
                </div>
                <div>
                    <Label for="new_customer_document_type">
                        {{ trans('app.fields.document_type') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <select
                        id="new_customer_document_type"
                        v-model="form.document_type"
                        class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                    >
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
                    <InputError class="mt-1" :message="errors.document_type" />
                </div>
                <div>
                    <Label for="new_customer_id_number">
                        {{ trans('app.fields.id_number') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="new_customer_id_number"
                        v-model="form.id_number"
                        name="id_number"
                        class="mt-1 w-full"
                        :placeholder="
                            trans('app.pos.customer_form.id_number_placeholder')
                        "
                    />
                    <InputError class="mt-1" :message="errors.id_number" />
                </div>
                <div>
                    <Label for="new_customer_phone">
                        {{ trans('app.fields.phone') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="new_customer_phone"
                        v-model="form.phone"
                        class="mt-1 w-full"
                        :placeholder="
                            trans('app.pos.customer_form.phone_placeholder')
                        "
                    />
                    <InputError class="mt-1" :message="errors.phone" />
                </div>
                <div>
                    <Label for="new_customer_email">{{
                        trans('app.fields.email')
                    }}</Label>
                    <Input
                        id="new_customer_email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 w-full"
                        :placeholder="
                            trans('app.pos.customer_form.email_placeholder')
                        "
                    />
                    <InputError class="mt-1" :message="errors.email" />
                </div>
                <div>
                    <Label for="new_customer_birth_date">{{
                        trans('app.fields.birth_date')
                    }}</Label>
                    <Input
                        id="new_customer_birth_date"
                        v-model="form.birth_date"
                        type="date"
                        :max="props.today"
                        class="mt-1 w-full"
                    />
                    <InputError class="mt-1" :message="errors.birth_date" />
                </div>
                <div>
                    <Label for="new_customer_address">{{
                        trans('app.fields.address')
                    }}</Label>
                    <Input
                        id="new_customer_address"
                        v-model="form.address"
                        class="mt-1 w-full"
                        :placeholder="
                            trans('app.pos.customer_form.address_placeholder')
                        "
                    />
                    <InputError class="mt-1" :message="errors.address" />
                </div>
                <div>
                    <Label for="new_customer_city">{{
                        trans('app.fields.city')
                    }}</Label>
                    <Input
                        id="new_customer_city"
                        v-model="form.city"
                        class="mt-1 w-full"
                        :placeholder="trans('app.fields.city')"
                    />
                    <InputError class="mt-1" :message="errors.city" />
                </div>
                <div class="sm:col-span-2">
                    <Label for="new_customer_notes">{{
                        trans('app.fields.notes')
                    }}</Label>
                    <textarea
                        id="new_customer_notes"
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                        :placeholder="
                            trans('app.pos.customer_form.notes_placeholder')
                        "
                    ></textarea>
                    <InputError class="mt-1" :message="errors.notes" />
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="onOpenChange(false)"
                >
                    {{ trans('app.pos.cancel') }}
                </Button>
                <Button type="button" :disabled="submitting" @click="save">
                    {{ trans('app.pos.customer_form.save_customer') }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
