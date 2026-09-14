import { ref } from 'vue';
import type { Ref } from 'vue';
import { csrfFetch } from '@/lib/csrfFetch';
import { store } from '@/routes/pos/customers';

export interface NewCustomerPayload {
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

export interface CreatedCustomer {
    id: number;
    name: string;
    last_name: string;
    id_number: string | null;
}

export function useCreateCustomer() {
    const errors: Ref<Record<string, string>> = ref({});
    const submitting = ref(false);

    async function submit(
        payload: NewCustomerPayload,
    ): Promise<CreatedCustomer | null> {
        submitting.value = true;
        errors.value = {};

        try {
            const response = await csrfFetch(store.url(), {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (response.status === 422) {
                const body = (await response.json()) as {
                    errors: Record<string, string[]>;
                };
                errors.value = Object.fromEntries(
                    Object.entries(body.errors).map(([key, messages]) => [
                        key,
                        messages[0],
                    ]),
                );

                return null;
            }

            if (!response.ok) {
                return null;
            }

            return (await response.json()) as CreatedCustomer;
        } finally {
            submitting.value = false;
        }
    }

    return { errors, submitting, submit };
}
