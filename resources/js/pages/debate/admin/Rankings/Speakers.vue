<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Award, Star } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { unwrapCollection } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import type { SpeakerRanking } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kedudukan',
                href: admin.rankings.teams().url,
            },
            {
                title: 'Pendebat',
                href: admin.rankings.speakers().url,
            },
        ],
    },
});

const http = useHttp();
const rankings = ref<SpeakerRanking[]>([]);
const loading = ref(true);

const fetchRankings = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.rankings.speakers().url);
        rankings.value = unwrapCollection<SpeakerRanking>(response);
    } catch (error) {
        rankings.value = [];
        console.error('Failed to load speaker rankings', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRankings);
</script>

<template>
    <Head title="Kedudukan Pendebat" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Kedudukan Pendebat" description="Prestasi individu pendebat merentas semua perlawanan." />
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="admin.rankings.teams().url">Kedudukan Pasukan</Link>
                </Button>
            </div>
        </div>

        <div class="relative w-full overflow-auto rounded-xl border bg-background">
            <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground w-16">Ked.</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Pendebat</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Pasukan</th>
                                <th class="h-12 px-4 text-center align-middle font-medium text-muted-foreground">Purata Markah</th>
                                <th class="h-12 px-4 text-center align-middle font-medium text-muted-foreground">Menang Pendebat Terbaik</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <template v-if="loading">
                                <tr v-for="i in 5" :key="i" class="border-b transition-colors">
                                    <td class="p-4 align-middle"><div class="h-4 w-8 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-32 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-24 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted mx-auto"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted mx-auto"></div></td>
                                </tr>
                            </template>
                            <tr v-else-if="rankings.length === 0" class="border-b transition-colors">
                                <td colspan="5" class="p-8 text-center text-muted-foreground">
                                    Tiada kedudukan tersedia. Lengkapkan perlawanan untuk melihat statistik individu.
                                </td>
                            </tr>
                            <tr v-for="(speaker, index) in rankings" :key="speaker.speaker_id" class="border-b transition-colors hover:bg-muted/50">
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
                                    <div class="font-bold flex items-center gap-2">
                                        {{ speaker.speaker_name }}
                                        <Star v-if="index === 0" class="w-3 h-3 fill-yellow-500 text-yellow-500" />
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="text-xs text-muted-foreground">{{ speaker.team_name }}</div>
                                </td>
                                <td class="p-4 align-middle text-center font-black text-lg text-primary">{{ Number(speaker.average_official_points_per_appearance).toFixed(1) }}</td>
                                <td class="p-4 align-middle text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Award v-if="speaker.best_speaker_wins_count > 0" class="w-3 h-3 text-amber-600" />
                                        <span>{{ speaker.best_speaker_wins_count }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
            </table>
        </div>
    </div>
</template>
