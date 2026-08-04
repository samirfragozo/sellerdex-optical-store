import { usePage } from '@inertiajs/vue3';

export function useTranslations() {
    const page = usePage();

    function trans(key: string): string {
        const value = key
            .split('.')
            .reduce<unknown>(
                (acc, segment) =>
                    acc && typeof acc === 'object'
                        ? (acc as Record<string, unknown>)[segment]
                        : undefined,
                page.props.translations,
            );

        return typeof value === 'string' ? value : key;
    }

    return { trans };
}
