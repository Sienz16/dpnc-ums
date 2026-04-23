<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LayoutGrid, Users, Calendar, MapPin, Shield, Trophy, FileText, Swords } from 'lucide-vue-next';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Papan Pemuka',
            href: dashboard().url,
            icon: LayoutGrid,
        },
    ];

    if (user.value?.role === 'superadmin') {
        items.push(
            {
                title: 'Hakim',
                href: '/debate/admin/judges',
                icon: Shield,
            },
            {
                title: 'Pusingan',
                href: '/debate/admin/rounds',
                icon: Calendar,
            },
            {
                title: 'Bilik',
                href: '/debate/admin/rooms',
                icon: MapPin,
            },
            {
                title: 'Pasukan',
                href: '/debate/admin/teams',
                icon: Users,
            },
            {
                title: 'Perlawanan',
                href: '/debate/admin/matches',
                icon: Swords,
            },
            {
                title: 'Kedudukan',
                href: '/debate/admin/rankings/teams',
                icon: Trophy,
            },
            {
                title: 'Laporan',
                href: '/debate/admin/reports/tournament',
                icon: FileText,
            }
        );
    } else if (user.value?.role === 'judge') {
        items.push({
            title: 'Perlawanan Saya',
            href: '/debate/judge/matches',
            icon: Swords,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repositori',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Dokumentasi',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
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
