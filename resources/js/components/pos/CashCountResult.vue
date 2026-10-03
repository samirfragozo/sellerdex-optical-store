<script setup lang="ts">
import { CircleCheck, TriangleAlert } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';
import type { CashCountLine } from '@/types/global';

defineProps<{ counts: CashCountLine[] }>();

const { trans } = useTranslations();

function formatCOP(value: number): string {
    return '$' + new Intl.NumberFormat('es-CO').format(value);
}

function label(difference: number): string {
    if (difference === 0) {
        return trans('app.pos.cash_session.difference_match');
    }

    return trans(
        difference > 0
            ? 'app.pos.cash_session.difference_over'
            : 'app.pos.cash_session.difference_short',
    );
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-muted-foreground">
                    <th scope="col" class="py-1 pr-2 font-normal">
                        {{ trans('app.fields.payment_method') }}
                    </th>
                    <th scope="col" class="px-2 py-1 text-right font-normal">
                        {{ trans('app.pos.cash_session.expected') }}
                    </th>
                    <th scope="col" class="px-2 py-1 text-right font-normal">
                        {{ trans('app.pos.cash_session.counted') }}
                    </th>
                    <th scope="col" class="py-1 pl-2 text-right font-normal">
                        {{ trans('app.pos.cash_session.difference') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="line in counts"
                    :key="line.payment_method_id"
                    class="border-t"
                >
                    <th scope="row" class="py-2 pr-2 text-left font-medium">
                        {{ line.name }}
                    </th>
                    <td class="px-2 py-2 text-right">
                        {{ formatCOP(line.expected) }}
                    </td>
                    <td class="px-2 py-2 text-right">
                        {{ formatCOP(line.counted) }}
                    </td>
                    <td
                        class="py-2 pl-2 text-right font-medium"
                        :class="
                            line.difference === 0
                                ? 'text-muted-foreground'
                                : 'text-destructive'
                        "
                    >
                        <span class="inline-flex items-center gap-1">
                            <CircleCheck
                                v-if="line.difference === 0"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <TriangleAlert
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ formatCOP(Math.abs(line.difference)) }}
                            <span class="text-xs font-normal">{{
                                label(line.difference)
                            }}</span>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
