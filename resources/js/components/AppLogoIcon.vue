<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Variant = 'auto' | 'inverted' | 'color' | 'blanco';

type Props = {
    className?: HTMLAttributes['class'];
    variant?: Variant;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'auto',
});

// auto: color mark on light backgrounds, white mark on dark backgrounds.
// inverted: the opposite, for badges whose background flips with the theme.
const colorVisibility = computed(
    () =>
        ({
            auto: 'dark:hidden',
            inverted: 'hidden dark:block',
            color: '',
            blanco: 'hidden',
        })[props.variant],
);

const blancoVisibility = computed(
    () =>
        ({
            auto: 'hidden dark:block',
            inverted: 'dark:hidden',
            color: 'hidden',
            blanco: '',
        })[props.variant],
);
</script>

<template>
    <img
        src="/images/brand/isotipo-color.png"
        alt="Sellerdex"
        :class="[className, colorVisibility, 'object-contain']"
        v-bind="$attrs"
    />
    <img
        src="/images/brand/isotipo-blanco.png"
        alt="Sellerdex"
        :class="[className, blancoVisibility, 'object-contain']"
        v-bind="$attrs"
    />
</template>
