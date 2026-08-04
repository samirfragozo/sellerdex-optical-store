<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/password/confirm';

const { trans } = useTranslations();

setLayoutProps({
    title: trans('auth.confirm_password.title'),
    description: trans('auth.confirm_password.description'),
});
</script>

<template>
    <Head :title="trans('auth.confirm_password.head_title')" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="trans('auth.confirm_password.passkey_label')"
        :loading-label="trans('auth.confirm_password.passkey_loading_label')"
        :separator="trans('auth.confirm_password.passkey_separator')"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{
                    trans('auth.confirm_password.password')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ trans('auth.confirm_password.submit') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
