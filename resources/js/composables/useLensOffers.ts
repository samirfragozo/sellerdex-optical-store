import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { lensOffers } from '@/routes/pos';

/** A lab able to make the chosen lens for the armado's prescription. */
export interface LensOffer {
    supplier_id: number;
    supplier_name: string;
    /** Lens price at this lab, installation included, before treatments. */
    price: number;
    cost: number;
    is_preferred: boolean;
}

export interface LensOffersQuery {
    lens_type_id: number;
    lens_technology_id: number;
    lens_material_id: number;
    prescription_id: number;
}

export function useLensOffers() {
    const { trans } = useTranslations();
    const offers = ref<LensOffer[]>([]);
    const message = ref<string | null>(null);
    const loading = ref(false);
    /** The request itself failed (as opposed to a server answer of "no offers"), so retrying makes sense. */
    const failed = ref(false);
    // Only the latest selection's answer counts — a slower, older request
    // must never overwrite it.
    let latest = 0;

    async function load(query: LensOffersQuery): Promise<void> {
        const current = ++latest;
        loading.value = true;
        message.value = null;
        failed.value = false;

        try {
            const response = await csrfFetch(
                lensOffers.url({ query: { ...query } }),
            );

            if (current !== latest) {
                return;
            }

            if (!response.ok) {
                offers.value = [];
                failed.value = true;
                message.value = trans('app.pos.lens_form.offers_error');

                return;
            }

            const body = (await response.json()) as {
                offers: LensOffer[];
                message: string | null;
            };
            offers.value = body.offers;
            message.value = body.message;
        } catch {
            if (current === latest) {
                offers.value = [];
                failed.value = true;
                message.value = trans('app.pos.lens_form.offers_error');
            }
        } finally {
            if (current === latest) {
                loading.value = false;
            }
        }
    }

    function clear(): void {
        latest++;
        offers.value = [];
        message.value = null;
        failed.value = false;
        loading.value = false;
    }

    return { offers, message, loading, failed, load, clear };
}
