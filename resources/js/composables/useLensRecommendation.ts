import { ref } from 'vue';
import type { Ref } from 'vue';
/** Shape returned by LensRecommender::recommend(). */
export interface LensRecommendation {
    design: string;
    process: string;
    material: string;
    filter: string;
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export function useLensRecommendation() {
    const recommended: Ref<LensRecommendation | null> = ref(null);
    const warnings: Ref<string[]> = ref([]);
    let timer: ReturnType<typeof setTimeout> | null = null;

    async function request(
        prescription: Record<string, unknown>,
        chosen: { design?: string; material?: string },
    ): Promise<void> {
        const res = await fetch('/pos/lens-recommendation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ prescription, chosen }),
        });

        if (!res.ok) {
            return;
        }

        const data = (await res.json()) as {
            recommended: LensRecommendation;
            warnings: string[];
        };
        recommended.value = data.recommended;
        warnings.value = data.warnings;
    }

    function fetchFor(
        prescription: Record<string, unknown>,
        chosen: { design?: string; material?: string } = {},
    ): Promise<void> {
        if (timer) {
            clearTimeout(timer);
        }

        return new Promise((resolve) => {
            timer = setTimeout(async () => {
                try {
                    await request(prescription, chosen);
                } finally {
                    resolve();
                }
            }, 300);
        });
    }

    return { recommended, warnings, fetchFor };
}
