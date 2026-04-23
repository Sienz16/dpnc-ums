<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { ArrowLeft, MapPin, Calendar, Shield, CheckCircle2, XCircle, AlertTriangle, RotateCcw, FastForward, FileText, Trophy, Check } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
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
import { speakerRoleLabel, sortMembersBySpeakerPosition } from '@/lib/debateSpeakers';
import { unwrapCollection, unwrapData } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import type { Match, User } from '@/types/debate';

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

const fetchMatchData = async () => {
    loading.value = true;

    try {
        const [mRes, jRes] = await Promise.all([
            http.get(admin.matches.show(props.matchId).url),
            http.get(admin.judges.index().url),
        ]);
        matchData.value = unwrapData<Match>(mRes);
        judges.value = unwrapCollection<User>(jRes);
        selectedJudges.value = matchData.value?.judge_assignments?.map((assignment) => assignment.judge_id) ?? [];
        isEditingAssignments.value = (matchData.value?.judge_assignments?.length ?? 0) === 0;
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

// Assignments
const selectedJudges = ref<number[]>([]);
const hasAssignedJudges = computed(() => (matchData.value?.judge_assignments?.length ?? 0) > 0);
const unavailableJudgeIds = computed(() => new Set(matchData.value?.unavailable_judge_ids ?? []));
const activeJudges = computed(() => {
    return judges.value.filter((judge) => judge.is_active && !unavailableJudgeIds.value.has(judge.id));
});

const saveManualAssignments = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        const assignHttp = useHttp({ judge_ids: selectedJudges.value });
        await assignHttp.post(admin.matches.assignments.manual(matchData.value.id).url);
        isEditingAssignments.value = false;
        fetchMatchData();
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
        fetchMatchData();
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
        fetchMatchData();
    } catch (error) {
        console.error('Failed to clear assignments', error);
    }
};

// Operations
const isForceCompleteDialogOpen = ref(false);
const isReopenDialogOpen = ref(false);
const isClearAssignmentsDialogOpen = ref(false);
const operationReason = ref('');

const forceComplete = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        const forceHttp = useHttp({ reason: operationReason.value });
        await forceHttp.post(admin.matches.forceComplete(matchData.value.id).url);
        isForceCompleteDialogOpen.value = false;
        operationReason.value = '';
        fetchMatchData();
    } catch (error) {
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
        fetchMatchData();
    } catch (error) {
        console.error('Failed to reopen match', error);
    }
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'pending': return 'secondary';
        case 'in_progress': return 'default';
        case 'completed': return 'success';
        default: return 'outline';
    }
};
</script>

<template>
    <Head title="Butiran Perlawanan" />

    <div class="p-6 space-y-6">
        <div v-if="!matchData && loading" class="text-sm text-muted-foreground">Memuatkan perlawanan...</div>
        <template v-else-if="matchData">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" as-child>
                    <Link :href="debate.admin.matches.index()">
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-semibold">Butiran Perlawanan</h1>
                    <div class="flex items-center gap-2 mt-1">
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
                        <FileText class="w-4 h-4 mr-2" />
                        Lihat Laporan
                    </Link>
                </Button>
                <Button v-if="matchData.status !== 'completed'" variant="destructive" @click="isForceCompleteDialogOpen = true">
                    <FastForward class="w-4 h-4 mr-2" />
                    Tamat Paksa
                </Button>
                <Button v-if="matchData.status === 'completed'" variant="outline" @click="isReopenDialogOpen = true">
                    <RotateCcw class="w-4 h-4 mr-2" />
                    Buka Semula
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <!-- Matchup Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Padanan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center justify-between p-4 rounded-lg bg-muted/30">
                            <div class="text-center flex-1">
                                <p class="text-xs text-muted-foreground mb-1 uppercase tracking-wider">Kerajaan</p>
                                <p class="text-xl font-bold text-primary">{{ matchData.government_team?.name }}</p>
                                <p class="text-xs text-muted-foreground mt-1">{{ matchData.government_team?.institution }}</p>
                            </div>
                            <div class="px-8 text-2xl font-black text-muted-foreground/50">VS</div>
                            <div class="text-center flex-1">
                                <p class="text-xs text-muted-foreground mb-1 uppercase tracking-wider">Pembangkang</p>
                                <p class="text-xl font-bold text-destructive">{{ matchData.opposition_team?.name }}</p>
                                <p class="text-xs text-muted-foreground mt-1">{{ matchData.opposition_team?.institution }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-6">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full bg-muted">
                                    <Calendar class="w-4 h-4" />
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">Pusingan</p>
                                    <p class="text-sm font-medium">{{ matchData.round?.name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full bg-muted">
                                    <MapPin class="w-4 h-4" />
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">Bilik</p>
                                    <p class="text-sm font-medium">{{ matchData.room?.name }}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Result Card (if completed) -->
                <Card v-if="matchData.result" class="border-primary/20 bg-primary/5">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Trophy class="w-5 h-5 text-yellow-500" />
                            Keputusan Rasmi
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="text-center py-4">
                            <p class="text-sm text-muted-foreground uppercase tracking-widest mb-2">Pemenang</p>
                            <h2 class="text-4xl font-black uppercase" :class="matchData.result.winner_side === 'government' ? 'text-primary' : 'text-destructive'">
                                {{ matchData.result.winner_side === 'government' ? matchData.government_team?.name : matchData.opposition_team?.name }}
                            </h2>
                            <p class="text-lg font-bold mt-2">
                                Pecahan Undi: {{ matchData.result.winner_vote_count }} - {{ matchData.result.loser_vote_count }}
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-4 border-t pt-6">
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground mb-1">Margin Rasmi</p>
                                <p class="text-xl font-bold">{{ Number(matchData.result.official_margin).toFixed(1) }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground mb-1">Markah Kerajaan</p>
                                <p class="text-xl font-bold">{{ Number(matchData.result.official_team_score_government).toFixed(1) }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground mb-1">Markah Pembangkang</p>
                                <p class="text-xl font-bold">{{ Number(matchData.result.official_team_score_opposition).toFixed(1) }}</p>
                            </div>
                        </div>

                        <div v-if="matchData.result.best_speaker" class="flex flex-col items-center border-t pt-6">
                            <p class="text-xs text-muted-foreground mb-2">Pendebat Terbaik</p>
                            <Badge variant="outline" class="text-base py-1 px-4">
                                {{ matchData.result.best_speaker.full_name }} ({{ matchData.result.best_speaker.speaker_position_label ?? speakerRoleLabel(matchData.result.best_speaker.speaker_position, matchData.result.best_speaker.team_id === matchData.government_team_id ? 'government' : 'opposition') }})
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Judge Assignments -->
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
                                <div v-for="judge in activeJudges" :key="judge.id" class="flex items-center space-x-2 border p-3 rounded-lg hover:bg-muted/50 cursor-pointer"
                                     @click="selectedJudges.includes(judge.id) ? selectedJudges = selectedJudges.filter(id => id !== judge.id) : (selectedJudges.length < matchData.judge_panel_size ? selectedJudges.push(judge.id) : null)">
                                    <div class="w-4 h-4 rounded border flex items-center justify-center" :class="selectedJudges.includes(judge.id) ? 'bg-primary border-primary' : 'border-input'">
                                        <Check v-if="selectedJudges.includes(judge.id)" class="w-3 h-3 text-white" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium truncate">{{ judge.name }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="selectedJudges.length !== matchData.judge_panel_size" class="flex items-center gap-2 text-amber-600 bg-amber-50 p-3 rounded-md text-sm">
                                <AlertTriangle class="w-4 h-4" />
                                Pilih tepat {{ matchData.judge_panel_size }} hakim. (Dipilih: {{ selectedJudges.length }})
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <div v-if="!hasAssignedJudges" class="text-sm text-muted-foreground">
                                Tiada hakim ditetapkan untuk perlawanan ini.
                            </div>
                            <div v-for="assignment in matchData.judge_assignments" :key="assignment.id" class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-full bg-muted">
                                        <Shield class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <p class="font-medium">{{ assignment.judge?.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ assignment.judge?.email }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-center">
                                        <p class="text-[10px] text-muted-foreground uppercase">Daftar Hadir</p>
                                        <CheckCircle2 v-if="assignment.checked_in_at" class="w-5 h-5 text-green-500 mx-auto" />
                                        <XCircle v-else class="w-5 h-5 text-muted-foreground/30 mx-auto" />
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[10px] text-muted-foreground uppercase">Dihantar</p>
                                        <CheckCircle2 v-if="assignment.submitted_at" class="w-5 h-5 text-green-500 mx-auto" />
                                        <XCircle v-else class="w-5 h-5 text-muted-foreground/30 mx-auto" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <!-- Team Rosters -->
                <Card>
                    <CardHeader>
                        <CardTitle>Senarai Pasukan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <p class="text-xs font-bold text-primary mb-2 uppercase tracking-widest">Kerajaan</p>
                            <div class="space-y-2">
                                <div v-for="member in sortMembersBySpeakerPosition(matchData.government_team?.members)" :key="member.id" class="flex items-center justify-between text-sm p-2 border rounded">
                                    <div>
                                        <p class="font-medium">{{ member.full_name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ speakerRoleLabel(member.speaker_position, 'government') }}</p>
                                    </div>
                                    <Badge variant="outline" class="text-[10px]">{{ member.speaker_position_label }}</Badge>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-destructive mb-2 uppercase tracking-widest">Pembangkang</p>
                            <div class="space-y-2">
                                <div v-for="member in sortMembersBySpeakerPosition(matchData.opposition_team?.members)" :key="member.id" class="flex items-center justify-between text-sm p-2 border rounded">
                                    <div>
                                        <p class="font-medium">{{ member.full_name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ speakerRoleLabel(member.speaker_position, 'opposition') }}</p>
                                    </div>
                                    <Badge variant="outline" class="text-[10px]">{{ member.speaker_position_label }}</Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Modals -->
        <Dialog v-model:open="isForceCompleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tamat Paksa Perlawanan</DialogTitle>
                    <DialogDescription>
                        Tindakan ini akan menamatkan perlawanan walaupun masih ada hakim yang belum menghantar borang markah. Sebab tindakan diperlukan untuk log audit.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label for="force-reason">Sebab tamat paksa</Label>
                    <Input id="force-reason" v-model="operationReason" placeholder="Contoh: masalah teknikal pada peranti hakim" class="mt-2" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isForceCompleteDialogOpen = false">Batal</Button>
                    <Button variant="destructive" @click="forceComplete" :disabled="!operationReason">Sahkan Tamat Paksa</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isReopenDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Buka Semula Perlawanan</DialogTitle>
                    <DialogDescription>
                        Tindakan ini akan menukar status semula kepada sedang berjalan. Hakim boleh menyunting semula borang markah. Sebab tindakan diperlukan.
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
