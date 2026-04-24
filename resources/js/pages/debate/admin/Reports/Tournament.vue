<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Download, Users, Swords, CheckCircle2, Shield, Trophy, Medal } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { unwrapCollection, unwrapData } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import type { Match, SpeakerRanking, TeamRanking, User } from '@/types/debate';

type SpeakerRankMode = 'markah' | 'pendebat_terbaik';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Laporan',
                href: admin.reports.tournament().url,
            },
            {
                title: 'Rumusan Kejohanan',
                href: admin.reports.tournament().url,
            },
        ],
    },
});

const http = useHttp();
const teamRankings = ref<TeamRanking[]>([]);
const speakerRankings = ref<SpeakerRanking[]>([]);
const matches = ref<Match[]>([]);
const judges = ref<User[]>([]);
const loading = ref(true);
const speakerRankMode = ref<SpeakerRankMode>('markah');

const fetchReport = async () => {
    loading.value = true;

    try {
        const [reportResponse, matchesResponse, judgesResponse] = await Promise.all([
            http.get(admin.reports.tournament().url),
            http.get(admin.matches.index().url),
            http.get(admin.judges.index().url),
        ]);

        const reportData = unwrapData<{ team_rankings?: TeamRanking[]; speaker_rankings?: SpeakerRanking[] }>(reportResponse);

        teamRankings.value = reportData?.team_rankings ?? [];
        speakerRankings.value = reportData?.speaker_rankings ?? [];
        matches.value = unwrapCollection<Match>(matchesResponse);
        judges.value = unwrapCollection<User>(judgesResponse);
    } catch (error) {
        teamRankings.value = [];
        speakerRankings.value = [];
        matches.value = [];
        judges.value = [];
        console.error('Failed to load tournament report', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchReport);

const summary = computed(() => ({
    total_teams: teamRankings.value.length,
    total_matches: matches.value.length,
    completed_matches: matches.value.filter((match) => match.status === 'completed').length,
    total_judges: judges.value.length,
}));

const topTeams = computed(() => teamRankings.value.slice(0, 10));
const topSpeakers = computed(() => {
    const items = [...speakerRankings.value];

    if (speakerRankMode.value === 'pendebat_terbaik') {
        return items
            .sort((left, right) => {
                if (right.best_speaker_wins_count !== left.best_speaker_wins_count) {
                    return right.best_speaker_wins_count - left.best_speaker_wins_count;
                }

                return right.average_official_points_per_appearance - left.average_official_points_per_appearance;
            })
            .slice(0, 10);
    }

    return items
        .sort((left, right) => {
            if (right.average_official_points_per_appearance !== left.average_official_points_per_appearance) {
                return right.average_official_points_per_appearance - left.average_official_points_per_appearance;
            }

            return right.best_speaker_wins_count - left.best_speaker_wins_count;
        })
        .slice(0, 10);
});

const printReport = (): void => {
    window.print();
};
</script>

<template>
    <Head title="Laporan Kejohanan" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Laporan Kejohanan" description="Rumusan menyeluruh bagi keseluruhan kejohanan." />
            <Button variant="outline" @click="printReport">
                <Download class="w-4 h-4 mr-2" />
                Cetak / Eksport PDF
            </Button>
        </div>

        <div v-if="loading" class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            <Card v-for="i in 4" :key="i" class="animate-pulse">
                <CardContent class="p-6">
                    <div class="h-4 w-20 bg-muted rounded mb-2"></div>
                    <div class="h-8 w-12 bg-muted rounded"></div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="space-y-6">
            <!-- Stats Overview -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-muted-foreground uppercase font-bold">Jumlah Pasukan</p>
                            <Users class="w-4 h-4 text-muted-foreground" />
                        </div>
                        <p class="text-3xl font-black mt-2">{{ summary.total_teams }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-muted-foreground uppercase font-bold">Jumlah Perlawanan</p>
                            <Swords class="w-4 h-4 text-muted-foreground" />
                        </div>
                        <p class="text-3xl font-black mt-2">{{ summary.total_matches }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-muted-foreground uppercase font-bold">Selesai</p>
                            <CheckCircle2 class="w-4 h-4 text-green-500" />
                        </div>
                        <p class="text-3xl font-black mt-2">{{ summary.completed_matches }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-muted-foreground uppercase font-bold">Jumlah Hakim</p>
                            <Shield class="w-4 h-4 text-muted-foreground" />
                        </div>
                        <p class="text-3xl font-black mt-2">{{ summary.total_judges }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Top Teams -->
                <section class="space-y-3">
                    <h2 class="text-base font-semibold flex items-center gap-2">
                        <Trophy class="w-4 h-4 text-yellow-500" />
                        10 Pasukan Teratas
                    </h2>
                    <div class="overflow-auto rounded-xl border bg-background">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium">Pasukan</th>
                                    <th class="px-4 py-2 text-center font-medium">Menang</th>
                                    <th class="px-4 py-2 text-center font-medium">Markah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="team in topTeams" :key="team.team_id" class="border-b last:border-0">
                                    <td class="px-4 py-3 font-medium">{{ team.team_name }}</td>
                                    <td class="px-4 py-3 text-center font-bold">{{ team.win_count }}</td>
                                    <td class="px-4 py-3 text-center text-muted-foreground">{{ Number(team.average_team_score).toFixed(1) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Top Speakers -->
                <section class="space-y-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <h2 class="text-base font-semibold flex items-center gap-2">
                            <Medal class="w-4 h-4 text-amber-600" />
                            10 Pendebat Teratas
                        </h2>
                        <div class="w-full space-y-2 sm:w-56">
                            <Label for="speaker-rank-mode">Rank</Label>
                            <Select v-model="speakerRankMode">
                                <SelectTrigger id="speaker-rank-mode">
                                    <SelectValue placeholder="Pilih ranking" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="markah">Markah</SelectItem>
                                    <SelectItem value="pendebat_terbaik">Pendebat Terbaik</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="overflow-auto rounded-xl border bg-background">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium">Pendebat</th>
                                    <th class="px-4 py-2 text-center font-medium">Purata Markah</th>
                                    <th class="px-4 py-2 text-center font-medium">Menang PT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="speaker in topSpeakers" :key="speaker.speaker_id" class="border-b last:border-0">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ speaker.speaker_name }}</div>
                                        <div class="text-[10px] text-muted-foreground">{{ speaker.team_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-primary">{{ Number(speaker.average_official_points_per_appearance).toFixed(1) }}</td>
                                    <td class="px-4 py-3 text-center text-muted-foreground">{{ speaker.best_speaker_wins_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
