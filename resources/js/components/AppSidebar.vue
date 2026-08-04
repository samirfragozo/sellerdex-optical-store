<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ShieldCheck, ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { index as posIndex } from '@/routes/pos';
import type { NavItem } from '@/types';

const page = usePage();
const { trans } = useTranslations();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: trans('app.pos.title'),
            href: posIndex(),
            icon: ShoppingCart,
        },
    ];

    if (page.props.auth?.is_admin) {
        items.push({
            title: trans('app.nav.admin'),
            href: '/admin',
            icon: ShieldCheck,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="posIndex()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
