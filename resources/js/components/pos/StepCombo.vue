<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/composables/useTranslations';

interface Combo {
    with_exam: boolean;
    estuche: 'small' | 'large';
    include_liquid: boolean;
    include_pano: boolean;
}

const combo = defineModel<Combo>('combo', { required: true });
const { trans } = useTranslations();
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Examen -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="combo.with_exam" />
            {{ trans('app.pos.combo_form.include_exam') }}
        </label>

        <!-- Estuche -->
        <div>
            <span class="mb-1 block text-sm font-medium">{{
                trans('app.pos.summary.lining')
            }}</span>
            <div class="flex gap-4">
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        v-model="combo.estuche"
                        type="radio"
                        value="small"
                        class="size-4 accent-primary"
                    />
                    {{ trans('app.pos.summary.small') }}
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        v-model="combo.estuche"
                        type="radio"
                        value="large"
                        class="size-4 accent-primary"
                    />
                    {{ trans('app.pos.summary.large') }}
                </label>
            </div>
        </div>

        <!-- Paño -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="combo.include_pano" />
            {{ trans('app.pos.combo_form.include_pano') }}
        </label>

        <!-- Líquido -->
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox v-model="combo.include_liquid" />
            {{ trans('app.pos.combo_form.include_liquid') }}
        </label>
    </div>
</template>
