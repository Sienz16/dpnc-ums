<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { unwrapCollection } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import type { TeamRanking } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kedudukan',
                href: admin.rankings.teams().url,
            },
            {
                title: 'Pasukan',
                href: admin.rankings.teams().url,
            },
        ],
    },
});

const http = useHttp();
const rankings = ref<TeamRanking[]>([]);
const loading = ref(true);

const fetchRankings = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.rankings.teams().url);
        rankings.value = unwrapCollection<TeamRanking>(response);
    } catch (error) {
        rankings.value = [];
        console.error('Failed to load team rankings', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRankings);
</script>

<template>
    <Head title="Kedudukan Pasukan" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Kedudukan Pasukan" description="Kedudukan keseluruhan kejohanan untuk semua pasukan." />
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="admin.rankings.speakers().url">Kedudukan Pendebat</Link>
                </Button>
            </div>
        </div>

        <div class="relative w-full overflow-auto rounded-xl border bg-background">
            <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground w-16">Ked.</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Pasukan</th>
                                <th class="h-12 px-4 text-center align-middle font-medium text-muted-foreground">Menang</th>
                                <th class="h-12 px-4 text-center align-middle font-medium text-muted-foreground">Hakim</th>
                                <th class="h-12 px-4 text-center align-middle font-medium text-muted-foreground">Purata Margin</th>
                                <th class="h-12 px-4 text-center align-middle font-medium text-muted-foreground">Purata Markah</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <template v-if="loading">
                                <tr v-for="i in 5" :key="i" class="border-b transition-colors">
                                    <td class="p-4 align-middle"><div class="h-4 w-8 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-32 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted mx-auto"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted mx-auto"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted mx-auto"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted mx-auto"></div></td>
                                </tr>
                            </template>
                            <tr v-else-if="rankings.length === 0" class="border-b transition-colors">
                                <td colspan="6" class="p-8 text-center text-muted-foreground">
                                    Tiada kedudukan tersedia. Lengkapkan perlawanan untuk melihat carta kedudukan.
                                </td>
                            </tr>
                            <tr v-for="(team, index) in rankings" :key="team.team_id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle font-bold">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full" 
                                         :class="{
                                             'bg-yellow-500/10 text-yellow-600': index === 0,
                                             'bg-slate-400/10 text-slate-500': index === 1,
                                             'bg-amber-600/10 text-amber-700': index === 2,
                                             'text-muted-foreground': index > 2
                                         }">
                                        {{ index + 1 }}
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="font-bold">{{ team.team_name }}</div>
                                </td>
                                <td class="p-4 align-middle text-center font-black text-lg">{{ team.win_count }}</td>
                                <td class="p-4 align-middle text-center">{{ team.judge_count }}</td>
                                <td class="p-4 align-middle text-center">{{ Number(team.average_margin).toFixed(1) }}</td>
                                <td class="p-4 align-middle text-center">{{ Number(team.average_team_score).toFixed(1) }}</td>
                            </tr>
                        </tbody>
            </table>
        </div>
    </div>
</template>
