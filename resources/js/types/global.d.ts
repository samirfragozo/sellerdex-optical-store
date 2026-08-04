import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

export interface CreatedSale {
    id: number;
    number: string;
    prescription_id: number | null;
    invoice_url: string;
    invoice_pdf_url: string;
    formula_url: string | null;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            business: { name: string };
            auth: Auth;
            sidebarOpen: boolean;
            flash: {
                success: string | null;
                createdSale: CreatedSale | null;
            };
            translations: Record<string, unknown>;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
