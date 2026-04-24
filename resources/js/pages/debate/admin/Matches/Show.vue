<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import {
    ArrowLeft,
    MapPin,
    Calendar,
    Shield,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    RotateCcw,
    FastForward,
    FileText,
    Trophy,
    Check,
    PencilLine,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { scoredMembers, speakerRoleLabel, sortMembersBySpeakerPosition } from '@/lib/debateSpeakers';
import { unwrapCollection, unwrapData } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import type { Match, ScoreSheet, TeamMember, User } from '@/types/debate';

type ScoreFieldKey =
    | 'mark_pm'
    | 'mark_tpm'
    | 'mark_m1'
    | 'mark_kp'
    | 'mark_tkp'
    | 'mark_p1'
    | 'mark_penggulungan_gov'
    | 'mark_penggulungan_opp';

interface ScoreFieldMeta {
    key: ScoreFieldKey;
    label: string;
    max: number;
    recommendedRange: string;
}

type LineupPosition = 'speaker_1' | 'speaker_2' | 'speaker_3' | 'speaker_4';
type LineupSideKey = 'government' | 'opposition';

const governmentScoreFields: ScoreFieldMeta[] = [
    { key: 'mark_pm', label: 'Perdana Menteri', max: 100, recommendedRange: '75-85' },
    { key: 'mark_tpm', label: 'Timbalan Perdana Menteri', max: 100, recommendedRange: '75-85' },
    { key: 'mark_m1', label: 'Menteri 1', max: 100, recommendedRange: '75-85' },
    { key: 'mark_penggulungan_gov', label: 'Penggulungan Kerajaan', max: 50, recommendedRange: '31-42' },
];

const oppositionScoreFields: ScoreFieldMeta[] = [
    { key: 'mark_kp', label: 'Ketua Pembangkang', max: 100, recommendedRange: '75-85' },
    { key: 'mark_tkp', label: 'Timbalan Ketua Pembangkang', max: 100, recommendedRange: '75-85' },
    { key: 'mark_p1', label: 'Pembangkang 1', max: 100, recommendedRange: '75-85' },
    { key: 'mark_penggulungan_opp', label: 'Penggulungan Pembangkang', max: 50, recommendedRange: '31-42' },
];

const props = defineProps<{
    matchId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Perlawanan',
                href: debate.admin.matches.index().url,
            },
            {
                title: 'Butiran Perlawanan',
                href: '#',
            },
        ],
    },
});

const http = useHttp();
const matchData = ref<Match | null>(null);
const judges = ref<User[]>([]);
const loading = ref(false);
const isEditingAssignments = ref(false);
const isForceCompleteDialogOpen = ref(false);
const isReopenDialogOpen = ref(false);
const isClearAssignmentsDialogOpen = ref(false);
const isScoreSheetDialogOpen = ref(false);
const operationReason = ref('');
const selectedJudges = ref<number[]>([]);
const activeScoreSheetJudgeId = ref<number | null>(null);
const isEditingLineup = ref(false);
const lineupForm = useHttp({
    government: {
        speaker_1: null as number | null,
        speaker_2: null as number | null,
        speaker_3: null as number | null,
        speaker_4: null as number | null,
    },
    opposition: {
        speaker_1: null as number | null,
        speaker_2: null as number | null,
        speaker_3: null as number | null,
        speaker_4: null as number | null,
    },
});
const lineupPositions: LineupPosition[] = ['speaker_1', 'speaker_2', 'speaker_3', 'speaker_4'];
let syncingLineupSwap = false;

const scoreSheetForm = useHttp({
    mark_pm: 0,
    mark_tpm: 0,
    mark_m1: 0,
    mark_kp: 0,
    mark_tkp: 0,
    mark_p1: 0,
    mark_penggulungan_gov: 0,
    mark_penggulungan_opp: 0,
    margin: 1,
    best_debater_member_id: null as number | null,
    reason: '',
});

const fetchMatchData = async () => {
    loading.value = true;

    try {
        const [matchResponse, judgesResponse] = await Promise.all([
            http.get(admin.matches.show(props.matchId).url),
            http.get(admin.judges.index().url),
        ]);

        matchData.value = unwrapData<Match>(matchResponse);
        applyLineupToForm(matchData.value);
        judges.value = unwrapCollection<User>(judgesResponse);
        selectedJudges.value = matchData.value?.judge_assignments?.map((assignment) => assignment.judge_id) ?? [];
        isEditingAssignments.value = (matchData.value?.judge_assignments?.length ?? 0) === 0;
        isEditingLineup.value = false;
    } catch (error) {
        matchData.value = null;
        judges.value = [];
        selectedJudges.value = [];
        console.error('Failed to load match details', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchMatchData);

const hasAssignedJudges = computed(() => (matchData.value?.judge_assignments?.length ?? 0) > 0);
const unavailableJudgeIds = computed(() => new Set(matchData.value?.unavailable_judge_ids ?? []));
const activeJudges = computed(() => judges.value.filter((judge) => judge.is_active && !unavailableJudgeIds.value.has(judge.id)));

const allMembers = computed(() => {
    if (!matchData.value) {
        return [] as TeamMember[];
    }

    return [
        ...(matchData.value.government_lineup ?? matchData.value.government_team?.members ?? []),
        ...(matchData.value.opposition_lineup ?? matchData.value.opposition_team?.members ?? []),
    ];
});

const eligibleBestDebaters = computed(() => scoredMembers(allMembers.value));
const governmentLineup = computed(() => sortMembersBySpeakerPosition(matchData.value?.government_lineup ?? matchData.value?.government_team?.members));
const oppositionLineup = computed(() => sortMembersBySpeakerPosition(matchData.value?.opposition_lineup ?? matchData.value?.opposition_team?.members));
const canEditLineup = computed(() => matchData.value?.can_edit_lineup ?? false);
const governmentRosterOptions = computed(() => sortMembersBySpeakerPosition(matchData.value?.government_team?.members));
const oppositionRosterOptions = computed(() => sortMembersBySpeakerPosition(matchData.value?.opposition_team?.members));

const scoreSheetsByJudgeId = computed(() => {
    return new Map((matchData.value?.score_sheets ?? []).map((sheet) => [sheet.judge_id, sheet]));
});

const missingJudgeAssignments = computed(() => {
    return (matchData.value?.judge_assignments ?? []).filter((assignment) => assignment.submitted_at === null);
});

const activeScoreSheetAssignment = computed(() => {
    return (matchData.value?.judge_assignments ?? []).find((assignment) => assignment.judge_id === activeScoreSheetJudgeId.value) ?? null;
});

const activeExistingScoreSheet = computed(() => {
    if (activeScoreSheetJudgeId.value === null) {
        return null;
    }

    return scoreSheetsByJudgeId.value.get(activeScoreSheetJudgeId.value) ?? null;
});

const forceCompleteBlocked = computed(() => missingJudgeAssignments.value.length > 0);

const memberIdByPosition = (members: TeamMember[] = [], position: TeamMember['speaker_position']): number | null => {
    return members.find((member) => member.speaker_position === position)?.id ?? null;
};

const lineupError = (sideKey: LineupSideKey, position: LineupPosition): string | undefined => {
    return lineupForm.errors[`${sideKey}.${position}` as keyof typeof lineupForm.errors];
};

const applyLineupToForm = (match: Match | null) => {
    lineupForm.government.speaker_1 = memberIdByPosition(match?.government_lineup ?? match?.government_team?.members, 'speaker_1');
    lineupForm.government.speaker_2 = memberIdByPosition(match?.government_lineup ?? match?.government_team?.members, 'speaker_2');
    lineupForm.government.speaker_3 = memberIdByPosition(match?.government_lineup ?? match?.government_team?.members, 'speaker_3');
    lineupForm.government.speaker_4 = memberIdByPosition(match?.government_lineup ?? match?.government_team?.members, 'speaker_4');
    lineupForm.opposition.speaker_1 = memberIdByPosition(match?.opposition_lineup ?? match?.opposition_team?.members, 'speaker_1');
    lineupForm.opposition.speaker_2 = memberIdByPosition(match?.opposition_lineup ?? match?.opposition_team?.members, 'speaker_2');
    lineupForm.opposition.speaker_3 = memberIdByPosition(match?.opposition_lineup ?? match?.opposition_team?.members, 'speaker_3');
    lineupForm.opposition.speaker_4 = memberIdByPosition(match?.opposition_lineup ?? match?.opposition_team?.members, 'speaker_4');
    lineupForm.clearErrors();
};

const syncLineupSwap = (
    sideKey: LineupSideKey,
    position: LineupPosition,
    newValue: number | null,
    oldValue: number | null,
) => {
    if (syncingLineupSwap) {
        return;
    }

    if (!newValue || newValue === oldValue) {
        return;
    }

    const side = lineupForm[sideKey];
    const duplicatePosition = lineupPositions.find((candidate) => {
        return candidate !== position && side[candidate] === newValue;
    });

    if (!duplicatePosition) {
        return;
    }

    syncingLineupSwap = true;
    side[duplicatePosition] = oldValue;
    syncingLineupSwap = false;
};

for (const position of lineupPositions) {
    watch(
        () => lineupForm.government[position],
        (newValue, oldValue) => {
            syncLineupSwap('government', position, newValue, oldValue);
        },
    );

    watch(
        () => lineupForm.opposition[position],
        (newValue, oldValue) => {
            syncLineupSwap('opposition', position, newValue, oldValue);
        },
    );
}

const applyScoreSheetToForm = (scoreSheet: ScoreSheet | null) => {
    scoreSheetForm.mark_pm = scoreSheet ? Number(scoreSheet.mark_pm) : 0;
    scoreSheetForm.mark_tpm = scoreSheet ? Number(scoreSheet.mark_tpm) : 0;
    scoreSheetForm.mark_m1 = scoreSheet ? Number(scoreSheet.mark_m1) : 0;
    scoreSheetForm.mark_kp = scoreSheet ? Number(scoreSheet.mark_kp) : 0;
    scoreSheetForm.mark_tkp = scoreSheet ? Number(scoreSheet.mark_tkp) : 0;
    scoreSheetForm.mark_p1 = scoreSheet ? Number(scoreSheet.mark_p1) : 0;
    scoreSheetForm.mark_penggulungan_gov = scoreSheet ? Number(scoreSheet.mark_penggulungan_gov) : 0;
    scoreSheetForm.mark_penggulungan_opp = scoreSheet ? Number(scoreSheet.mark_penggulungan_opp) : 0;
    scoreSheetForm.margin = scoreSheet ? Number(scoreSheet.margin) : 1;
    scoreSheetForm.best_debater_member_id = scoreSheet?.best_debater_member_id ?? null;
    scoreSheetForm.reason = '';
    scoreSheetForm.clearErrors();
};

const openScoreSheetDialog = (judgeId: number) => {
    activeScoreSheetJudgeId.value = judgeId;
    applyScoreSheetToForm(scoreSheetsByJudgeId.value.get(judgeId) ?? null);
    isScoreSheetDialogOpen.value = true;
};

const closeScoreSheetDialog = () => {
    isScoreSheetDialogOpen.value = false;
    activeScoreSheetJudgeId.value = null;
    applyScoreSheetToForm(null);
};

const scoreSheetForJudge = (judgeId: number): ScoreSheet | null => {
    return scoreSheetsByJudgeId.value.get(judgeId) ?? null;
};

const saveAdminScoreSheet = async () => {
    if (!matchData.value || !activeScoreSheetAssignment.value) {
        return;
    }

    try {
        await scoreSheetForm.patch(admin.matches.scoreSheets.update({
            match: matchData.value.id,
            judge: activeScoreSheetAssignment.value.judge_id,
        }).url);
        await fetchMatchData();
        closeScoreSheetDialog();
        toast.success('Borang markah hakim berjaya dikemas kini.');
    } catch (error) {
        toast.error('Gagal menyimpan borang markah hakim. Sila semak input dan cuba lagi.');
        console.error('Failed to save admin score sheet', error);
    }
};

const saveLineup = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        await lineupForm.patch(admin.matches.lineup.update(matchData.value.id).url);
        await fetchMatchData();
        isEditingLineup.value = false;
        toast.success('Lineup perlawanan berjaya dikemas kini.');
    } catch (error) {
        toast.error('Gagal mengemas kini lineup perlawanan.');
        console.error('Failed to save lineup', error);
    }
};

const startEditingLineup = () => {
    if (!matchData.value || !canEditLineup.value) {
        return;
    }

    applyLineupToForm(matchData.value);
    isEditingLineup.value = true;
};

const cancelEditingLineup = () => {
    applyLineupToForm(matchData.value);
    lineupForm.clearErrors();
    isEditingLineup.value = false;
};

const saveManualAssignments = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        const assignHttp = useHttp({ judge_ids: selectedJudges.value });
        await assignHttp.post(admin.matches.assignments.manual(matchData.value.id).url);
        isEditingAssignments.value = false;
        await fetchMatchData();
    } catch (error) {
        console.error('Failed to save assignments', error);
    }
};

const randomizeAssignments = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        const randomHttp = useHttp({
            eligible_judge_ids: activeJudges.value.map((judge) => judge.id),
        });
        await randomHttp.post(admin.matches.assignments.randomize(matchData.value.id).url);
        isEditingAssignments.value = false;
        await fetchMatchData();
    } catch (error) {
        console.error('Failed to randomize assignments', error);
    }
};

const startEditingAssignments = () => {
    if (!matchData.value) {
        return;
    }

    selectedJudges.value = matchData.value.judge_assignments?.map((assignment) => assignment.judge_id) ?? [];
    isEditingAssignments.value = true;
};

const cancelEditingAssignments = () => {
    isEditingAssignments.value = false;
    selectedJudges.value = matchData.value?.judge_assignments?.map((assignment) => assignment.judge_id) ?? [];
};

const clearAssignments = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        await http.delete(admin.matches.assignments.clear(matchData.value.id).url);
        selectedJudges.value = [];
        isEditingAssignments.value = true;
        isClearAssignmentsDialogOpen.value = false;
        await fetchMatchData();
    } catch (error) {
        console.error('Failed to clear assignments', error);
    }
};

const forceComplete = async () => {
    if (!matchData.value || forceCompleteBlocked.value) {
        return;
    }

    try {
        const forceHttp = useHttp({ reason: operationReason.value });
        await forceHttp.post(admin.matches.forceComplete(matchData.value.id).url);
        isForceCompleteDialogOpen.value = false;
        operationReason.value = '';
        await fetchMatchData();
        toast.success('Perlawanan berjaya ditamatkan secara paksa.');
    } catch (error) {
        toast.error('Gagal menamatkan perlawanan secara paksa.');
        console.error('Failed to force complete', error);
    }
};

const reopenMatch = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        const reopenHttp = useHttp({ reason: operationReason.value });
        await reopenHttp.post(admin.matches.reopen(matchData.value.id).url);
        isReopenDialogOpen.value = false;
        operationReason.value = '';
        await fetchMatchData();
        toast.success('Perlawanan dibuka semula untuk pembetulan.');
    } catch (error) {
        toast.error('Gagal membuka semula perlawanan.');
        console.error('Failed to reopen match', error);
    }
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'pending':
            return 'secondary';
        case 'in_progress':
            return 'default';
        case 'completed':
            return 'success';
        default:
            return 'outline';
    }
};

const speakerNameForField = (field: ScoreFieldMeta, side: 'government' | 'opposition'): string | null => {
    const lineup = side === 'government' ? governmentLineup.value : oppositionLineup.value;

    if (field.key === 'mark_penggulungan_gov' || field.key === 'mark_penggulungan_opp') {
        return null;
    }

    const indexByField: Record<string, number> = {
        mark_pm: 0,
        mark_tpm: 1,
        mark_m1: 2,
        mark_kp: 0,
        mark_tkp: 1,
        mark_p1: 2,
    };

    return lineup[indexByField[field.key]]?.full_name ?? null;
};
</script>

<template>
    <Head title="Butiran Perlawanan" />

    <div class="space-y-6 p-6">
        <div v-if="!matchData && loading" class="text-sm text-muted-foreground">Memuatkan perlawanan...</div>
        <template v-else-if="matchData">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="outline" size="icon" as-child>
                        <Link :href="debate.admin.matches.index()">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold">Butiran Perlawanan</h1>
                        <div class="mt-1 flex items-center gap-2">
                            <Badge :variant="getStatusVariant(matchData.status)">
                                {{ matchData.status === 'pending' ? 'Belum Bermula' : matchData.status === 'in_progress' ? 'Sedang Berjalan' : 'Selesai' }}
                            </Badge>
                            <Badge v-if="matchData.completion_type === 'force_completed'" variant="destructive">
                                Tamat Paksa
                            </Badge>
                            <Badge v-if="matchData.result_state === 'provisional'" variant="outline">
                                Sementara
                            </Badge>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button v-if="matchData.status === 'completed'" variant="outline" as-child>
                        <Link :href="debate.admin.reports.matches.show(matchData.id)">
                            <FileText class="mr-2 h-4 w-4" />
                            Lihat Laporan
                        </Link>
                    </Button>
                    <Button v-if="matchData.status !== 'completed'" variant="destructive" @click="isForceCompleteDialogOpen = true">
                        <FastForward class="mr-2 h-4 w-4" />
                        Tamat Paksa
                    </Button>
                    <Button v-if="matchData.status === 'completed'" variant="outline" @click="isReopenDialogOpen = true">
                        <RotateCcw class="mr-2 h-4 w-4" />
                        Buka Semula
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Padanan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between rounded-lg bg-muted/30 p-4">
                                <div class="flex-1 text-center">
                                    <p class="mb-1 text-xs uppercase tracking-wider text-muted-foreground">Kerajaan</p>
                                    <p class="text-xl font-bold text-primary">{{ matchData.government_team?.name }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ matchData.government_team?.institution }}</p>
                                </div>
                                <div class="px-8 text-2xl font-black text-muted-foreground/50">VS</div>
                                <div class="flex-1 text-center">
                                    <p class="mb-1 text-xs uppercase tracking-wider text-muted-foreground">Pembangkang</p>
                                    <p class="text-xl font-bold text-destructive">{{ matchData.opposition_team?.name }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ matchData.opposition_team?.institution }}</p>
                                </div>
                            </div>

                            <div class="mt-6 grid grid-cols-2 gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-full bg-muted p-2">
                                        <Calendar class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground">Pusingan</p>
                                        <p class="text-sm font-medium">{{ matchData.round?.name }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="rounded-full bg-muted p-2">
                                        <MapPin class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground">Bilik</p>
                                        <p class="text-sm font-medium">{{ matchData.room?.name }}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="matchData.result" class="border-primary/20 bg-primary/5">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Trophy class="h-5 w-5 text-yellow-500" />
                                Keputusan Rasmi
                            </CardTitle>
                            <CardDescription>Keputusan ini akan dikira semula secara automatik apabila admin membetulkan borang markah hakim.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="py-4 text-center">
                                <p class="mb-2 text-sm uppercase tracking-widest text-muted-foreground">Pemenang</p>
                                <h2 class="text-4xl font-black uppercase" :class="matchData.result.winner_side === 'government' ? 'text-primary' : 'text-destructive'">
                                    {{ matchData.result.winner_side === 'government' ? matchData.government_team?.name : matchData.opposition_team?.name }}
                                </h2>
                                <p class="mt-2 text-lg font-bold">
                                    Pecahan Undi: {{ matchData.result.winner_vote_count }} - {{ matchData.result.loser_vote_count }}
                                </p>
                            </div>

                            <div class="grid grid-cols-3 gap-4 border-t pt-6">
                                <div class="text-center">
                                    <p class="mb-1 text-xs text-muted-foreground">Margin Rasmi</p>
                                    <p class="text-xl font-bold">{{ Number(matchData.result.official_margin).toFixed(1) }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="mb-1 text-xs text-muted-foreground">Markah Kerajaan</p>
                                    <p class="text-xl font-bold">{{ Number(matchData.result.official_team_score_government).toFixed(1) }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="mb-1 text-xs text-muted-foreground">Markah Pembangkang</p>
                                    <p class="text-xl font-bold">{{ Number(matchData.result.official_team_score_opposition).toFixed(1) }}</p>
                                </div>
                            </div>

                            <div v-if="matchData.result.best_speaker" class="flex flex-col items-center border-t pt-6">
                                <p class="mb-2 text-xs text-muted-foreground">Pendebat Terbaik</p>
                                <Badge variant="outline" class="px-4 py-1 text-base">
                                    {{ matchData.result.best_speaker.full_name }} ({{ matchData.result.best_speaker.speaker_position_label ?? speakerRoleLabel(matchData.result.best_speaker.speaker_position, matchData.result.best_speaker.team_id === matchData.government_team_id ? 'government' : 'opposition') }})
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Panel Hakim</CardTitle>
                                <CardDescription>Sasaran panel: {{ matchData.judge_panel_size }} hakim</CardDescription>
                            </div>
                            <div v-if="matchData.status === 'pending'" class="flex gap-2">
                                <template v-if="!hasAssignedJudges || isEditingAssignments">
                                    <Button v-if="!hasAssignedJudges" variant="outline" size="sm" @click="randomizeAssignments">
                                        Tentukan Secara Rawak
                                    </Button>
                                    <Button size="sm" @click="saveManualAssignments" :disabled="selectedJudges.length !== matchData.judge_panel_size">
                                        Simpan Panel
                                    </Button>
                                    <Button v-if="hasAssignedJudges" variant="destructive" size="sm" @click="isClearAssignmentsDialogOpen = true">
                                        Buang Semua Hakim
                                    </Button>
                                    <Button v-if="hasAssignedJudges" variant="outline" size="sm" @click="cancelEditingAssignments">
                                        Batal
                                    </Button>
                                </template>
                                <template v-else>
                                    <Button size="sm" @click="startEditingAssignments">
                                        Ubah Panel Hakim
                                    </Button>
                                </template>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="matchData.status === 'pending' && (!hasAssignedJudges || isEditingAssignments)" class="space-y-4">
                                <div v-if="activeJudges.length === 0" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                                    Tiada hakim tersedia untuk pusingan ini. Semua hakim aktif telah ditugaskan ke sidang lain.
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div
                                        v-for="judge in activeJudges"
                                        :key="judge.id"
                                        class="flex cursor-pointer items-center space-x-2 rounded-lg border p-3 hover:bg-muted/50"
                                        @click="selectedJudges.includes(judge.id) ? selectedJudges = selectedJudges.filter((id) => id !== judge.id) : (selectedJudges.length < matchData.judge_panel_size ? selectedJudges.push(judge.id) : null)"
                                    >
                                        <div class="flex h-4 w-4 items-center justify-center rounded border" :class="selectedJudges.includes(judge.id) ? 'border-primary bg-primary' : 'border-input'">
                                            <Check v-if="selectedJudges.includes(judge.id)" class="h-3 w-3 text-white" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium">{{ judge.name }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="selectedJudges.length !== matchData.judge_panel_size" class="flex items-center gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-600">
                                    <AlertTriangle class="h-4 w-4" />
                                    Pilih tepat {{ matchData.judge_panel_size }} hakim. (Dipilih: {{ selectedJudges.length }})
                                </div>
                            </div>

                            <div v-else class="space-y-4">
                                <div
                                    v-if="missingJudgeAssignments.length > 0"
                                    class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                                >
                                    <p class="font-medium">Ada hakim yang belum menghantar borang markah.</p>
                                    <p class="mt-1">Lengkapkan borang untuk hakim berikut sebelum tamat paksa:</p>
                                    <ul class="mt-2 space-y-1">
                                        <li v-for="assignment in missingJudgeAssignments" :key="assignment.id">
                                            {{ assignment.judge?.name }}
                                        </li>
                                    </ul>
                                </div>
                                <div v-if="!hasAssignedJudges" class="text-sm text-muted-foreground">
                                    Tiada hakim ditetapkan untuk perlawanan ini.
                                </div>
                                <div v-for="assignment in matchData.judge_assignments" :key="assignment.id" class="rounded-lg border p-4">
                                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="rounded-full bg-muted p-2">
                                                <Shield class="h-4 w-4" />
                                            </div>
                                            <div>
                                                <p class="font-medium">{{ assignment.judge?.name }}</p>
                                                <p class="text-xs text-muted-foreground">{{ assignment.judge?.email }}</p>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <Badge v-if="scoreSheetForJudge(assignment.judge_id)" variant="outline">
                                                Borang {{ scoreSheetForJudge(assignment.judge_id)?.state === 'submitted' ? 'Lengkap' : 'Draf' }}
                                            </Badge>
                                            <div class="text-center">
                                                <p class="text-[10px] uppercase text-muted-foreground">Daftar Hadir</p>
                                                <CheckCircle2 v-if="assignment.checked_in_at" class="mx-auto h-5 w-5 text-green-500" />
                                                <XCircle v-else class="mx-auto h-5 w-5 text-muted-foreground/30" />
                                            </div>
                                            <div class="text-center">
                                                <p class="text-[10px] uppercase text-muted-foreground">Dihantar</p>
                                                <CheckCircle2 v-if="assignment.submitted_at" class="mx-auto h-5 w-5 text-green-500" />
                                                <XCircle v-else class="mx-auto h-5 w-5 text-muted-foreground/30" />
                                            </div>
                                            <Button size="sm" variant="outline" @click="openScoreSheetDialog(assignment.judge_id)">
                                                <PencilLine class="mr-2 h-4 w-4" />
                                                {{ scoreSheetForJudge(assignment.judge_id) ? 'Betulkan Markah' : 'Isi Markah' }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader class="flex flex-row items-start justify-between gap-3">
                            <div>
                                <CardTitle>Lineup Perlawanan</CardTitle>
                                <CardDescription>
                                    Susun lineup khusus untuk perlawanan ini. Perubahan hanya dibenarkan sebelum sebarang borang markah diwujudkan.
                                </CardDescription>
                            </div>
                            <Button v-if="canEditLineup && !isEditingLineup" size="sm" @click="startEditingLineup">
                                Edit Lineup
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div
                                v-if="!canEditLineup"
                                class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                            >
                                Lineup tidak boleh diubah lagi kerana perlawanan ini sudah selesai atau borang markah telah mula diwujudkan.
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-primary">Kerajaan</p>
                                <div v-if="canEditLineup && isEditingLineup" class="space-y-3">
                                    <div v-for="member in governmentLineup" :key="member.id" class="hidden"></div>
                                    <div v-for="position in lineupPositions" :key="`gov-${position}`" class="space-y-2">
                                        <Label :for="`gov-${position}`">{{ position === 'speaker_1' ? 'Pendebat 1' : position === 'speaker_2' ? 'Pendebat 2' : position === 'speaker_3' ? 'Pendebat 3' : 'Pendebat 4 (Simpanan)' }}</Label>
                                        <Select v-model="lineupForm.government[position]" :name="`government.${position}`">
                                            <SelectTrigger :id="`gov-${position}`" class="w-full">
                                                <SelectValue placeholder="Pilih ahli pasukan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="member in governmentRosterOptions" :key="member.id" :value="member.id">
                                                    {{ member.full_name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p v-if="lineupError('government', position)" class="text-[11px] text-destructive">
                                            {{ lineupError('government', position) }}
                                        </p>
                                    </div>
                                </div>
                                <div v-else class="space-y-2">
                                    <div v-for="member in governmentLineup" :key="member.id" class="flex items-center justify-between rounded border p-2 text-sm">
                                        <div>
                                            <p class="font-medium">{{ member.full_name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ speakerRoleLabel(member.speaker_position, 'government') }}</p>
                                        </div>
                                        <Badge variant="outline" class="text-[10px]">{{ member.speaker_position_label }}</Badge>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-destructive">Pembangkang</p>
                                <div v-if="canEditLineup && isEditingLineup" class="space-y-3">
                                    <div v-for="position in lineupPositions" :key="`opp-${position}`" class="space-y-2">
                                        <Label :for="`opp-${position}`">{{ position === 'speaker_1' ? 'Pendebat 1' : position === 'speaker_2' ? 'Pendebat 2' : position === 'speaker_3' ? 'Pendebat 3' : 'Pendebat 4 (Simpanan)' }}</Label>
                                        <Select v-model="lineupForm.opposition[position]" :name="`opposition.${position}`">
                                            <SelectTrigger :id="`opp-${position}`" class="w-full">
                                                <SelectValue placeholder="Pilih ahli pasukan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="member in oppositionRosterOptions" :key="member.id" :value="member.id">
                                                    {{ member.full_name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p v-if="lineupError('opposition', position)" class="text-[11px] text-destructive">
                                            {{ lineupError('opposition', position) }}
                                        </p>
                                    </div>
                                </div>
                                <div v-else class="space-y-2">
                                    <div v-for="member in oppositionLineup" :key="member.id" class="flex items-center justify-between rounded border p-2 text-sm">
                                        <div>
                                            <p class="font-medium">{{ member.full_name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ speakerRoleLabel(member.speaker_position, 'opposition') }}</p>
                                        </div>
                                        <Badge variant="outline" class="text-[10px]">{{ member.speaker_position_label }}</Badge>
                                    </div>
                                </div>
                            </div>
                            <div v-if="canEditLineup && isEditingLineup" class="flex justify-end gap-2">
                                <Button variant="outline" @click="cancelEditingLineup" :disabled="lineupForm.processing">
                                    Batal
                                </Button>
                                <Button @click="saveLineup" :disabled="lineupForm.processing">
                                    Simpan Lineup
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog v-model:open="isScoreSheetDialogOpen">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>
                            {{ activeExistingScoreSheet ? 'Betulkan Borang Markah Hakim' : 'Isi Borang Markah Hakim' }}
                        </DialogTitle>
                        <DialogDescription>
                            <span v-if="activeScoreSheetAssignment?.judge">
                                Rekod ini akan dihantar bagi pihak {{ activeScoreSheetAssignment.judge.name }} dan terus mengemas kini keputusan perlawanan.
                            </span>
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-6 py-2">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <Card class="border-primary/20">
                                <CardHeader class="bg-primary/5 pb-4">
                                    <CardTitle class="text-base text-primary">Bahagian Kerajaan</CardTitle>
                                    <CardDescription>Maksimum dan julat cadangan ikut borang manual.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4 p-6">
                                    <div
                                        v-for="field in governmentScoreFields"
                                        :key="field.key"
                                        class="grid grid-cols-[minmax(0,1fr)_8rem] items-center gap-4"
                                        :class="field.key === 'mark_penggulungan_gov' ? 'border-t pt-4' : ''"
                                    >
                                        <div>
                                            <Label class="font-medium">
                                                {{ field.label }}
                                                <span v-if="speakerNameForField(field, 'government')"> - {{ speakerNameForField(field, 'government') }}</span>
                                            </Label>
                                            <p class="mt-1 text-[11px] text-muted-foreground">
                                                Maks {{ field.max }} • Julat cadangan {{ field.recommendedRange }}
                                            </p>
                                            <p v-if="scoreSheetForm.errors[field.key]" class="mt-1 text-[11px] text-destructive">
                                                {{ scoreSheetForm.errors[field.key] }}
                                            </p>
                                        </div>
                                        <Input
                                            v-model="scoreSheetForm[field.key]"
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            :max="field.max"
                                            class="text-right"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card class="border-destructive/20">
                                <CardHeader class="bg-destructive/5 pb-4">
                                    <CardTitle class="text-base text-destructive">Bahagian Pembangkang</CardTitle>
                                    <CardDescription>Rujuk julat cadangan dalam kurungan semasa membetulkan markah.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4 p-6">
                                    <div
                                        v-for="field in oppositionScoreFields"
                                        :key="field.key"
                                        class="grid grid-cols-[minmax(0,1fr)_8rem] items-center gap-4"
                                        :class="field.key === 'mark_penggulungan_opp' ? 'border-t pt-4' : ''"
                                    >
                                        <div>
                                            <Label class="font-medium">
                                                {{ field.label }}
                                                <span v-if="speakerNameForField(field, 'opposition')"> - {{ speakerNameForField(field, 'opposition') }}</span>
                                            </Label>
                                            <p class="mt-1 text-[11px] text-muted-foreground">
                                                Maks {{ field.max }} • Julat cadangan {{ field.recommendedRange }}
                                            </p>
                                            <p v-if="scoreSheetForm.errors[field.key]" class="mt-1 text-[11px] text-destructive">
                                                {{ scoreSheetForm.errors[field.key] }}
                                            </p>
                                        </div>
                                        <Input
                                            v-model="scoreSheetForm[field.key]"
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            :max="field.max"
                                            class="text-right"
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                            <div class="space-y-2">
                                <Label for="best-debater-member-id">Pendebat Terbaik</Label>
                                <Select v-model="scoreSheetForm.best_debater_member_id">
                                    <SelectTrigger id="best-debater-member-id">
                                        <SelectValue placeholder="Pilih pendebat terbaik" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="member in eligibleBestDebaters" :key="member.id" :value="member.id">
                                            {{ member.full_name }} ({{ speakerRoleLabel(member.speaker_position, member.team_id === matchData.government_team_id ? 'government' : 'opposition') }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="scoreSheetForm.errors.best_debater_member_id" class="text-[11px] text-destructive">
                                    {{ scoreSheetForm.errors.best_debater_member_id }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="admin-margin">Margin</Label>
                                <Input id="admin-margin" v-model="scoreSheetForm.margin" type="number" step="0.5" min="1" max="8" />
                                <p class="text-[11px] text-muted-foreground">Julat yang dibenarkan: 1-8</p>
                                <p v-if="scoreSheetForm.errors.margin" class="text-[11px] text-destructive">
                                    {{ scoreSheetForm.errors.margin }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="score-sheet-reason">Sebab pembetulan / kemasukan pihak admin</Label>
                            <Input id="score-sheet-reason" v-model="scoreSheetForm.reason" placeholder="Contoh: akaun hakim bermasalah / kesilapan markah dikesan" />
                            <p v-if="scoreSheetForm.errors.reason" class="text-[11px] text-destructive">
                                {{ scoreSheetForm.errors.reason }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeScoreSheetDialog">Batal</Button>
                        <Button @click="saveAdminScoreSheet" :disabled="scoreSheetForm.processing || !scoreSheetForm.reason || !scoreSheetForm.best_debater_member_id">
                            Simpan Borang Hakim
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="isForceCompleteDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tamat Paksa Perlawanan</DialogTitle>
                        <DialogDescription>
                            Tindakan ini akan menutup perlawanan dengan rekod audit. Jika masih ada hakim yang belum menghantar, admin perlu isi borang mereka dahulu pada kad Panel Hakim.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <div v-if="forceCompleteBlocked" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <p class="font-medium">Force complete dikunci sementara.</p>
                            <p class="mt-1">Lengkapkan borang markah untuk hakim berikut dahulu:</p>
                            <ul class="mt-2 space-y-1">
                                <li v-for="assignment in missingJudgeAssignments" :key="assignment.id">
                                    {{ assignment.judge?.name }}
                                </li>
                            </ul>
                        </div>
                        <div>
                            <Label for="force-reason">Sebab tamat paksa</Label>
                            <Input id="force-reason" v-model="operationReason" placeholder="Contoh: masalah teknikal pada peranti hakim" class="mt-2" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="isForceCompleteDialogOpen = false">Batal</Button>
                        <Button variant="destructive" @click="forceComplete" :disabled="!operationReason || forceCompleteBlocked">
                            Sahkan Tamat Paksa
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="isReopenDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Buka Semula Perlawanan</DialogTitle>
                        <DialogDescription>
                            Tindakan ini akan menukar status semula kepada sedang berjalan. Hakim atau admin boleh membetulkan semula borang markah. Sebab tindakan diperlukan.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4">
                        <Label for="reopen-reason">Sebab buka semula</Label>
                        <Input id="reopen-reason" v-model="operationReason" placeholder="Contoh: kesilapan ditemui pada borang markah" class="mt-2" />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="isReopenDialogOpen = false">Batal</Button>
                        <Button @click="reopenMatch" :disabled="!operationReason">Sahkan Buka Semula</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="isClearAssignmentsDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle class="text-destructive">Buang Semua Hakim?</DialogTitle>
                        <DialogDescription>
                            Tindakan ini akan nyahlampir semua hakim daripada sidang ini dan memadam borang markah semasa (jika ada).
                            Anda perlu tetapkan semula panel hakim selepas ini.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" @click="isClearAssignmentsDialogOpen = false">Batal</Button>
                        <Button variant="destructive" @click="clearAssignments">Ya, Buang Semua</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </template>
    </div>
</template>
