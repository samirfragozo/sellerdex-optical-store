<script setup lang="ts">
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        /** Force a fixed sign (e.g. '+' for Add) and hide the toggle. */
        fixedSign?: '+' | '-' | null;
        min?: number;
        max?: number;
        step?: number;
        placeholder?: string;
    }>(),
    {
        fixedSign: null,
        min: 0,
        max: 20,
        step: 0.25,
        placeholder: '0.00',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

function parseSign(value: string | null): '+' | '-' {
    if (props.fixedSign) {
        return props.fixedSign;
    }
    return value?.trim().startsWith('-') ? '-' : '+';
}

function parseMagnitude(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }
    return value.replace(/^[+-]/, '').trim();
}

const sign = ref<'+' | '-'>(parseSign(props.modelValue));
const magnitude = ref<string>(parseMagnitude(props.modelValue));

// Keep internal state in sync when the bound value changes externally (e.g. form reset).
watch(
    () => props.modelValue,
    (value) => {
        sign.value = parseSign(value);
        magnitude.value = parseMagnitude(value);
    },
);

function emitValue(): void {
    const mag = magnitude.value.trim();
    emit('update:modelValue', mag === '' ? '' : `${props.fixedSign ?? sign.value}${mag}`);
}

function setSign(next: '+' | '-'): void {
    sign.value = next;
    emitValue();
}

function signClass(value: '+' | '-'): string {
    return [
        'flex h-9 w-8 items-center justify-center border border-input text-sm font-semibold transition-colors',
        sign.value === value ? 'bg-primary text-primary-foreground' : 'bg-transparent hover:bg-accent',
    ].join(' ');
}
</script>

<template>
    <div class="flex">
        <!-- Fixed sign (e.g. Add is always positive) -->
        <span
            v-if="fixedSign"
            class="flex h-9 w-8 items-center justify-center rounded-l-md border border-r-0 border-input bg-muted text-sm font-semibold text-muted-foreground"
        >
            {{ fixedSign === '-' ? '−' : '+' }}
        </span>

        <!-- Sign toggle -->
        <template v-else>
            <button type="button" :class="[signClass('-'), 'rounded-l-md']" @click="setSign('-')">−</button>
            <button type="button" :class="signClass('+')" @click="setSign('+')">+</button>
        </template>

        <input
            v-model="magnitude"
            type="number"
            inputmode="decimal"
            :min="min"
            :max="max"
            :step="step"
            :placeholder="placeholder"
            class="border-input dark:bg-input/30 h-9 w-full min-w-0 rounded-r-md border bg-transparent px-2 py-1 text-right text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
            @input="emitValue"
        />
    </div>
</template>
