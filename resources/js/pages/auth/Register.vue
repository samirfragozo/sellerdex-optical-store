<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';
import { store } from '@/routes/register';

const { trans } = useTranslations();

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: trans('auth.register.title'),
        description: trans('auth.register.description'),
    },
});
</script>

<template>
    <Head :title="trans('auth.register.head_title')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="company_name">{{
                    trans('auth.register.company_name')
                }}</Label>
                <Input
                    id="company_name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="organization"
                    name="company_name"
                    :placeholder="
                        trans('auth.register.company_name_placeholder')
                    "
                />
                <InputError :message="errors.company_name" />
            </div>

            <div class="grid gap-2">
                <Label for="name">{{ trans('auth.register.name') }}</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    :tabindex="2"
                    autocomplete="name"
                    name="name"
                    :placeholder="trans('auth.register.name_placeholder')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ trans('auth.register.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="3"
                    autocomplete="email"
                    name="email"
                    :placeholder="trans('auth.register.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{
                    trans('auth.register.password')
                }}</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password"
                    :placeholder="trans('auth.register.password_placeholder')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    trans('auth.register.confirm_password')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :placeholder="
                        trans('auth.register.confirm_password_placeholder')
                    "
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="6"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ trans('auth.register.submit') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ trans('auth.register.has_account') }}
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
                >{{ trans('auth.register.log_in') }}</TextLink
            >
        </div>
    </Form>
</template>
