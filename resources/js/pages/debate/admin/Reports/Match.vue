<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { ArrowLeft, Download, Trophy } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { speakerRoleLabel } from '@/lib/debateSpeakers';
import { unwrapData } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import type { Match } from '@/types/debate';

const props = defineProps<{
    matchId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Perlawanan',
                href: admin.matches.index().url,
            },
            {
                title: 'Laporan Perlawanan',
                href: '#',
            },
        ],
    },
});

const http = useHttp();
const report = ref<Match | null>(null);
const loading = ref(true);

const fetchReport = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.reports.matches.show(props.matchId).url);
        report.value = unwrapData<Match>(response);
    } catch (error) {
        report.value = null;
        console.error('Failed to load match report', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchReport);

const printReport = (): void => {
    window.print();
};
</script>

<template>
    <Head title="Laporan Perlawanan" />

    <div class="p-6 space-y-6 max-w-4xl mx-auto">
        <div class="flex items-center justify-between print:hidden">
            <Button variant="outline" size="sm" as-child>
                <Link :href="debate.admin.matches.show(matchId)">
                    <ArrowLeft class="w-4 h-4 mr-2" />
                    Kembali ke Perlawanan
                </Link>
            </Button>
            <Button variant="outline" @click="printReport">
                <Download class="w-4 h-4 mr-2" />
                Cetak Laporan
            </Button>
        </div>

        <div v-if="loading" class="space-y-6">
            <Card class="animate-pulse">
                <CardContent class="h-64 bg-muted/20"></CardContent>
            </Card>
        </div>

        <div v-else-if="report" class="space-y-8 bg-white p-8 rounded-xl border shadow-sm dark:bg-slate-950">
            <!-- Header -->
            <div class="text-center space-y-2 border-b pb-8">
                <h1 class="text-3xl font-black uppercase tracking-tighter">Laporan Perlawanan</h1>
                <p class="text-muted-foreground">{{ report.round?.name }} • {{ report.room?.name }}</p>
                <div class="flex justify-center gap-4 mt-4">
                    <Badge variant="outline">{{ report.scheduled_at ? new Date(report.scheduled_at).toLocaleString() : '-' }}</Badge>
                    <Badge v-if="report.completion_type === 'force_completed'" variant="destructive">Dimuktamadkan Secara Paksa</Badge>
                </div>
            </div>

            <!-- Result Summary -->
            <div class="grid grid-cols-3 gap-8 py-4">
                <div class="text-center space-y-2">
                    <p class="text-[10px] uppercase font-bold text-muted-foreground">Pemenang</p>
                    <p class="text-2xl font-black" :class="report.result?.winner_side === 'government' ? 'text-primary' : 'text-destructive'">
                        {{ report.result?.winner_side === 'government' ? report.government_team?.name : report.opposition_team?.name }}
                    </p>
                </div>
                <div class="text-center space-y-2">
                    <p class="text-[10px] uppercase font-bold text-muted-foreground">Pecahan Undi</p>
                    <p class="text-2xl font-black">{{ report.result?.winner_vote_count }} - {{ report.result?.loser_vote_count }}</p>
                </div>
                <div class="text-center space-y-2">
                    <p class="text-[10px] uppercase font-bold text-muted-foreground">Margin Rasmi</p>
                    <p class="text-2xl font-black">{{ report.result?.official_margin.toFixed(1) }}</p>
                </div>
            </div>

            <!-- Score Table -->
            <div class="space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-widest border-l-4 border-primary pl-2">Markah Individu</h3>
                <div class="border rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left">Hakim</th>
                                <th class="px-4 py-3 text-center">Pemenang</th>
                                <th class="px-4 py-3 text-center">Jumlah Kerajaan</th>
                                <th class="px-4 py-3 text-center">Jumlah Pembangkang</th>
                                <th class="px-4 py-3 text-center">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sheet in report.score_sheets" :key="sheet.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ sheet.judge?.name }}</td>
                                <td class="px-4 py-3 text-center">
                                    <Badge :variant="sheet.winner_side === 'government' ? 'default' : 'destructive'" class="text-[10px]">
                                        {{ sheet.winner_side === 'government' ? 'KERAJAAN' : 'PEMBANGKANG' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-center">{{ Number(sheet.gov_total).toFixed(1) }}</td>
                                <td class="px-4 py-3 text-center">{{ Number(sheet.opp_total).toFixed(1) }}</td>
                                <td class="px-4 py-3 text-center font-bold">{{ Number(sheet.margin).toFixed(1) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-muted/20 font-bold">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-right uppercase text-[10px]">Purata</td>
                                <td class="px-4 py-3 text-center">{{ report.result?.official_team_score_government.toFixed(1) }}</td>
                                <td class="px-4 py-3 text-center">{{ report.result?.official_team_score_opposition.toFixed(1) }}</td>
                                <td class="px-4 py-3 text-center text-primary">{{ report.result?.official_margin.toFixed(1) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Best Speaker -->
            <div v-if="report.result?.best_speaker" class="flex flex-col items-center p-6 bg-muted/20 rounded-xl border-2 border-dashed">
                <Trophy class="w-8 h-8 text-yellow-500 mb-2" />
                <p class="text-[10px] uppercase font-bold text-muted-foreground">Pendebat Terbaik</p>
                <p class="text-xl font-black mt-1">{{ report.result.best_speaker.full_name }}</p>
                <p class="text-xs text-muted-foreground">{{ report.result.best_speaker.speaker_position_label ?? speakerRoleLabel(report.result.best_speaker.speaker_position, report.result.best_speaker.team_id === report.government_team_id ? 'government' : 'opposition') }}</p>
            </div>
        </div>
    </div>
</template>
