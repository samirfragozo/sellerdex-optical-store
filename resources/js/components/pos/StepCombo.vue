<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';

interface Combo {
    with_exam: boolean;
    forro: 'small' | 'large';
    include_liquid: boolean;
}

const combo = defineModel<Combo>('combo', { required: true });
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Examen -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="combo.with_exam" />
            Incluir examen (gratis)
        </label>

        <!-- Forro -->
        <div>
            <span class="mb-1 block text-sm font-medium">Forro</span>
            <div class="flex gap-4">
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        v-model="combo.forro"
                        type="radio"
                        value="small"
                        class="size-4 accent-primary"
                    />
                    Pequeño
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        v-model="combo.forro"
                        type="radio"
                        value="large"
                        class="size-4 accent-primary"
                    />
                    Grande
                </label>
            </div>
        </div>

        <!-- Líquido -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="combo.include_liquid" />
            Incluir líquido
        </label>
    </div>
</template>
