import { ref } from 'vue';
import type { Ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { store } from '@/routes/pos/prescriptions';
import type { PrescriptionOption } from '@/types';

export interface NewPrescriptionPayload {
    customer_id: number;
    exam_date: string;
    prescriber_name: string;
    prescriber_license: string;
    notes: string;
    attachment: File | null;
    od_sphere: string;
    od_cylinder: string;
    od_axis: string;
    od_add: string;
    od_prism: string;
    od_prism_base: string;
    od_pd: string;
    od_va: string;
    os_sphere: string;
    os_cylinder: string;
    os_axis: string;
    os_add: string;
    os_prism: string;
    os_prism_base: string;
    os_pd: string;
    os_va: string;
}

export function useCreatePrescription() {
    const errors: Ref<Record<string, string>> = ref({});
    const submitting = ref(false);
    const { trans } = useTranslations();

    async function submit(
        payload: NewPrescriptionPayload,
    ): Promise<PrescriptionOption | null> {
        submitting.value = true;
        errors.value = {};

        const entries = (
            Object.entries(payload) as [string, string | number | File | null][]
        ).filter(([, value]) => value !== null && value !== '');

        // Only build a multipart body when a file is actually attached — it
        // needs multipart to carry the binary data. Otherwise a plain
        // urlencoded body is lighter and avoids the multipart boundary
        // entirely, which some intermediaries (e.g. the browser test
        // driver's request bridge) don't parse.
        const body = payload.attachment
            ? entries.reduce((formData, [key, value]) => {
                  formData.append(
                      key,
                      value instanceof File ? value : String(value),
                  );

                  return formData;
              }, new FormData())
            : new URLSearchParams(
                  entries.map(([key, value]) => [key, String(value)]),
              );

        try {
            const response = await csrfFetch(store.url(), {
                method: 'POST',
                body,
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
                errors.value = {
                    general: trans(
                        'app.pos.prescription_form.unexpected_error',
                    ),
                };

                return null;
            }

            return (await response.json()) as PrescriptionOption;
        } catch {
            errors.value = {
                general: trans('app.pos.prescription_form.unexpected_error'),
            };

            return null;
        } finally {
            submitting.value = false;
        }
    }

    return { errors, submitting, submit };
}
