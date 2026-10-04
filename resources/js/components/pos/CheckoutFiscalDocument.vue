<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { usePosCheckout } from '@/composables/usePosCheckout';
import { emptyBuyer } from '@/composables/usePosCheckout';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { fiscalData } from '@/routes/pos/customers';

const { trans } = useTranslations();

const props = defineProps<{
    checkout: ReturnType<typeof usePosCheckout>;
    customerId: number | null;
}>();

const DOCUMENT_TYPES = ['pos_electronic', 'electronic_invoice'] as const;
const PERSON_TYPES = ['natural', 'legal'] as const;
const RESPONSIBILITIES = ['O-13', 'O-15', 'O-23', 'O-47', 'R-99-PN'] as const;

const BUYER_FIELDS = [
    'person_type',
    'email',
    'dane_municipality_code',
    'fiscal_responsibilities',
];

// null while the customer's data is loading.
const missing = ref<string[] | null>(null);

const isInvoice = computed(
    () => props.checkout.fiscalDocumentType.value === 'electronic_invoice',
);
const showBuyer = computed(() => isInvoice.value && props.customerId !== null);

function errorFor(field: string): string | undefined {
    return props.checkout.errors.value[`buyer.${field}`];
}

function showField(field: string): boolean {
    return (
        (missing.value ?? []).includes(field) || errorFor(field) !== undefined
    );
}

function describedBy(field: string): string | undefined {
    return errorFor(field) ? `buyer_${field}_error` : undefined;
}

// Prefill the buyer with what the customer already has; the server only
// persists what is missing, so unchanged known values are harmless.
watch(
    [() => props.customerId, isInvoice],
    async ([customerId, invoice]) => {
        if (!invoice || customerId === null) {
            return;
        }

        // eslint-disable-next-line vue/no-mutating-props
        props.checkout.buyer.value = emptyBuyer();
        missing.value = null;

        try {
            const response = await csrfFetch(fiscalData.url(customerId));

            if (props.customerId !== customerId) {
                return;
            }

            if (!response.ok) {
                missing.value = BUYER_FIELDS;

                return;
            }

            const data = (await response.json()) as ReturnType<
                typeof emptyBuyer
            > & { missing: string[] };
            // eslint-disable-next-line vue/no-mutating-props
            props.checkout.buyer.value = {
                person_type: data.person_type,
                email: data.email,
                dane_municipality_code: data.dane_municipality_code,
                fiscal_responsibilities: data.fiscal_responsibilities,
            };
            missing.value = data.missing;
        } catch {
            // Show every field; the server still validates the buyer on submit.
            missing.value = BUYER_FIELDS;
        }
    },
    { immediate: true },
);

function selectDocument(type: string): void {
    // eslint-disable-next-line vue/no-mutating-props
    props.checkout.fiscalDocumentType.value = type;
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label>{{ trans('app.pos.fiscal.title') }}</Label>
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="type in DOCUMENT_TYPES"
                :key="type"
                type="button"
                size="sm"
                :variant="
                    checkout.fiscalDocumentType.value === type
                        ? 'default'
                        : 'outline'
                "
                :aria-pressed="checkout.fiscalDocumentType.value === type"
                :data-testid="`fiscal-document-${type}`"
                @click="selectDocument(type)"
            >
                {{ trans(`app.fiscal_document_type.${type}`) }}
            </Button>
        </div>

        <p
            v-if="isInvoice && customerId === null"
            class="text-sm text-muted-foreground"
        >
            {{ trans('app.pos.fiscal.final_consumer_note') }}
        </p>

        <div
            v-if="showBuyer"
            class="flex flex-col gap-3 rounded-md border border-sidebar-border/70 p-3 dark:border-sidebar-border"
        >
            <p class="text-sm font-medium">
                {{ trans('app.pos.fiscal.buyer_title') }}
            </p>
            <p
                v-if="
                    missing !== null &&
                    missing.length === 0 &&
                    Object.keys(checkout.errors.value).every(
                        (key) => !key.startsWith('buyer.'),
                    )
                "
                class="text-sm text-muted-foreground"
            >
                {{ trans('app.pos.fiscal.buyer_complete') }}
            </p>

            <!-- eslint-disable vue/no-mutating-props -->
            <div v-if="showField('person_type')" class="flex flex-col gap-1">
                <Label for="buyer_person_type">{{
                    trans('app.fields.person_type')
                }}</Label>
                <select
                    id="buyer_person_type"
                    v-model="checkout.buyer.value.person_type"
                    data-testid="buyer-person-type"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
                    :aria-describedby="describedBy('person_type')"
                >
                    <option :value="null" disabled></option>
                    <option
                        v-for="personType in PERSON_TYPES"
                        :key="personType"
                        :value="personType"
                    >
                        {{ trans(`app.person_type.${personType}`) }}
                    </option>
                </select>
                <InputError
                    id="buyer_person_type_error"
                    :message="errorFor('person_type')"
                />
            </div>

            <div v-if="showField('email')" class="flex flex-col gap-1">
                <Label for="buyer_email">{{ trans('app.fields.email') }}</Label>
                <Input
                    id="buyer_email"
                    :model-value="checkout.buyer.value.email ?? ''"
                    @update:model-value="
                        checkout.buyer.value.email = String($event) || null
                    "
                    type="email"
                    data-testid="buyer-email"
                    :aria-describedby="describedBy('email')"
                />
                <InputError
                    id="buyer_email_error"
                    :message="errorFor('email')"
                />
            </div>

            <div
                v-if="showField('dane_municipality_code')"
                class="flex flex-col gap-1"
            >
                <Label for="buyer_dane_code">{{
                    trans('app.fields.dane_municipality_code')
                }}</Label>
                <Input
                    id="buyer_dane_code"
                    :model-value="
                        checkout.buyer.value.dane_municipality_code ?? ''
                    "
                    @update:model-value="
                        checkout.buyer.value.dane_municipality_code =
                            String($event) || null
                    "
                    data-testid="buyer-dane-code"
                    inputmode="numeric"
                    maxlength="5"
                    :aria-describedby="
                        describedBy('dane_municipality_code') ??
                        'buyer_dane_code_help'
                    "
                />
                <p
                    id="buyer_dane_code_help"
                    class="text-sm text-muted-foreground"
                >
                    {{ trans('app.fields.dane_municipality_code_help') }}
                </p>
                <InputError
                    id="buyer_dane_municipality_code_error"
                    :message="errorFor('dane_municipality_code')"
                />
            </div>

            <fieldset
                v-if="showField('fiscal_responsibilities')"
                class="flex flex-col gap-1"
                :aria-describedby="describedBy('fiscal_responsibilities')"
            >
                <legend class="text-sm leading-none font-medium">
                    {{ trans('app.fields.fiscal_responsibilities') }}
                </legend>
                <label
                    v-for="code in RESPONSIBILITIES"
                    :key="code"
                    class="flex cursor-pointer items-center gap-2 py-1 text-sm"
                >
                    <input
                        v-model="checkout.buyer.value.fiscal_responsibilities"
                        type="checkbox"
                        :value="code"
                        :data-testid="`buyer-responsibility-${code}`"
                        class="size-4 accent-primary"
                    />
                    {{ trans(`app.fiscal_responsibility.${code}`) }}
                </label>
                <InputError
                    id="buyer_fiscal_responsibilities_error"
                    :message="errorFor('fiscal_responsibilities')"
                />
            </fieldset>
            <!-- eslint-enable vue/no-mutating-props -->
        </div>
    </div>
</template>
