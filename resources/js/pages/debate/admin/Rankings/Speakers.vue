<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Award, Star } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { unwrapCollection } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import type { Round, SpeakerRanking } from '@/types/debate';

type SpeakerRankingFilter = 'highest_mark' | 'highest_best_speaker_win';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kedudukan',
                href: debate.admin.rankings.teams().url,
            },
            {
                title: 'Pendebat',
                href: debate.admin.rankings.speakers().url,
            },
        ],
    },
});

const http = useHttp();
const rankings = ref<SpeakerRanking[]>([]);
const rounds = ref<Round[]>([]);
const loading = ref(true);
const roundsLoading = ref(true);
const rankingFilter = ref<SpeakerRankingFilter>('highest_mark');
const selectedRoundIds = ref<number[]>([]);

const speakerRankingsUrl = () => {
    const url = new URL(admin.rankings.speakers().url, window.location.origin);
    selectedRoundIds.value.forEach((roundId) => url.searchParams.append('round_ids[]', String(roundId)));

    return `${url.pathname}${url.search}`;
};

const fetchRounds = async () => {
    roundsLoading.value = true;

    try {
        const response = await http.get(admin.rounds.index().url);
        rounds.value = unwrapCollection<Round>(response);
    } catch (error) {
        rounds.value = [];
        console.error('Failed to load rounds', error);
    } finally {
        roundsLoading.value = false;
    }
};

const fetchRankings = async () => {
    loading.value = true;

    try {
        const response = await http.get(speakerRankingsUrl());
        rankings.value = unwrapCollection<SpeakerRanking>(response);
    } catch (error) {
        rankings.value = [];
        console.error('Failed to load speaker rankings', error);
    } finally {
        loading.value = false;
    }
};

const toggleRound = (roundId: number) => {
    selectedRoundIds.value = selectedRoundIds.value.includes(roundId)
        ? selectedRoundIds.value.filter((id) => id !== roundId)
        : [...selectedRoundIds.value, roundId];
};

const selectAllRounds = () => {
    selectedRoundIds.value = [];
};

onMounted(() => {
    fetchRounds();
    fetchRankings();
});
watch(selectedRoundIds, fetchRankings);

const filteredRankings = computed(() => {
    const items = [...rankings.value];

    if (rankingFilter.value === 'highest_best_speaker_win') {
        return items.sort((left, right) => {
            if (right.best_speaker_wins_count !== left.best_speaker_wins_count) {
                return right.best_speaker_wins_count - left.best_speaker_wins_count;
            }

            if (right.average_official_points_per_appearance !== left.average_official_points_per_appearance) {
                return right.average_official_points_per_appearance - left.average_official_points_per_appearance;
            }

            return right.average_score_per_appearance - left.average_score_per_appearance;
        });
    }

    return items.sort((left, right) => {
        if (right.average_official_points_per_appearance !== left.average_official_points_per_appearance) {
            return right.average_official_points_per_appearance - left.average_official_points_per_appearance;
        }

        if (right.best_speaker_wins_count !== left.best_speaker_wins_count) {
            return right.best_speaker_wins_count - left.best_speaker_wins_count;
        }

        return right.average_score_per_appearance - left.average_score_per_appearance;
    });
});
</script>

<template>
    <Head title="Kedudukan Pendebat" />

    <div class="p-6 space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <Heading title="Kedudukan Pendebat" description="Prestasi individu pendebat merentas semua perlawanan." />
            <div class="flex w-full flex-col gap-4 xl:w-auto xl:items-end">
                <div class="w-full space-y-2 xl:w-[45rem]">
                    <Label>Skop Pusingan</Label>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" :data-active="selectedRoundIds.length === 0" class="data-[active=true]:bg-primary data-[active=true]:text-primary-foreground" @click="selectAllRounds">
                            Semua Pusingan
                        </Button>
                        <Button
                            v-for="round in rounds"
                            :key="round.id"
                            type="button"
                            variant="outline"
                            size="sm"
                            :data-active="selectedRoundIds.includes(round.id)"
                            class="data-[active=true]:bg-primary data-[active=true]:text-primary-foreground"
                            @click="toggleRound(round.id)"
                        >
                            {{ round.name }}
                        </Button>
                        <div v-if="roundsLoading" class="h-8 w-28 animate-pulse rounded-md bg-muted"></div>
                    </div>
                </div>
                <div class="flex w-full flex-col gap-3 xl:flex-row xl:items-end xl:justify-end">
                    <div class="min-w-0 flex-1 space-y-2 xl:w-72 xl:flex-none">
                        <Label for="speaker-ranking-filter">Tapis Kedudukan</Label>
                        <Select v-model="rankingFilter">
                            <SelectTrigger id="speaker-ranking-filter">
                                <SelectValue placeholder="Pilih susunan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="highest_mark">
                                    Markah Tertinggi
                                </SelectItem>
                                <SelectItem value="highest_best_speaker_win">
                                    Menang Pendebat Terbaik Tertinggi
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button variant="outline" as-child class="w-full xl:w-auto">
                        <Link :href="debate.admin.rankings.teams().url">Kedudukan Pasukan</Link>
                    </Button>
                </div>
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
                            <tr v-else-if="filteredRankings.length === 0" class="border-b transition-colors">
                                <td colspan="5" class="p-8 text-center text-muted-foreground">
                                    Tiada kedudukan tersedia. Lengkapkan perlawanan untuk melihat statistik individu.
                                </td>
                            </tr>
                            <tr v-for="(speaker, index) in filteredRankings" :key="speaker.speaker_id" class="border-b transition-colors hover:bg-muted/50">
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
