<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { csrfFetch } from '@/lib/csrfFetch';
import { note } from '@/routes/pos/cash-sessions';

const props = defineProps<{ sessionId: number }>();
const emit = defineEmits<{ saved: [] }>();

const { trans } = useTranslations();

const notes = ref('');
const error = ref<string | undefined>();
const submitting = ref(false);

async function submit(): Promise<void> {
    submitting.value = true;
    error.value = undefined;

    try {
        const response = await csrfFetch(note.url(props.sessionId), {
            method: 'POST',
            body: JSON.stringify({ notes: notes.value }),
        });

        if (response.ok) {
            emit('saved');

            return;
        }

        const body = (await response.json()) as {
            message: string;
            errors?: Record<string, string[]>;
        };
        error.value = body.errors?.notes?.[0] ?? body.message;
    } catch {
        error.value = trans('app.pos.checkout.unexpected_error');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <form class="flex flex-col gap-2" @submit.prevent="submit">
        <Label for="close_note">{{
            trans('app.pos.cash_session.note_required_notice')
        }}</Label>
        <textarea
            id="close_note"
            v-model="notes"
            rows="2"
            required
            class="w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 dark:bg-input/30"
        ></textarea>
        <InputError :message="error" />
        <Button
            type="submit"
            variant="secondary"
            class="self-end"
            :disabled="submitting"
        >
            {{ trans('app.pos.cash_session.save_note') }}
        </Button>
    </form>
</template>
