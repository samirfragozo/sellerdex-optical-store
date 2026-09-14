<script setup lang="ts">
import { Delete } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

const { trans } = useTranslations();

const props = defineProps<{ modelValue: number }>();
const emit = defineEmits<{ 'update:modelValue': [number] }>();

const digits = [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    'clear',
    '0',
    'back',
];

function press(key: string): void {
    if (key === 'clear') {
        emit('update:modelValue', 0);

        return;
    }

    if (key === 'back') {
        emit('update:modelValue', Math.floor(props.modelValue / 10));

        return;
    }

    emit('update:modelValue', props.modelValue * 10 + Number(key));
}
</script>

<template>
    <div class="grid grid-cols-3 gap-2">
        <button
            v-for="key in digits"
            :key="key"
            type="button"
            class="flex h-12 items-center justify-center rounded-md border border-input bg-transparent text-lg font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring/50"
            :aria-label="
                key === 'clear'
                    ? trans('app.pos.checkout.keypad_clear')
                    : key === 'back'
                      ? trans('app.pos.checkout.keypad_backspace')
                      : key
            "
            @click="press(key)"
        >
            <Delete v-if="key === 'back'" class="size-5" />
            <span v-else-if="key === 'clear'" class="text-sm">{{
                trans('app.pos.checkout.keypad_clear')
            }}</span>
            <span v-else>{{ key }}</span>
        </button>
    </div>
</template>
