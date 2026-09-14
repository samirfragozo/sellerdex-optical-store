import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { CashRegisterSession } from '@/types/global';

// Module-level state: a singleton shared by every component that imports this
// composable (the user menu, the POS page, and the close modal all need to
// see the same open/closed session).
const session = ref<CashRegisterSession | null>(null);
const closeModalOpen = ref(false);
let initialized = false;

export function useCashRegisterSession() {
    if (!initialized) {
        session.value = usePage().props.cashRegisterSession;
        initialized = true;
    }

    function onSessionOpened(opened: CashRegisterSession): void {
        session.value = opened;
    }

    function onSessionClosed(): void {
        session.value = null;
        closeModalOpen.value = false;
    }

    function openCloseModal(): void {
        closeModalOpen.value = true;
    }

    return {
        session,
        closeModalOpen,
        onSessionOpened,
        onSessionClosed,
        openCloseModal,
    };
}
