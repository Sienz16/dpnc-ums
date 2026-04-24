<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, Shield, CheckCircle2, Save, Send, AlertTriangle } from 'lucide-vue-next';
import { onMounted, ref, computed, watchEffect } from 'vue';
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
import { unwrapData } from '@/lib/httpPayload';
import debate from '@/routes/debate';
import judge from '@/routes/judge';
import type { Match, ScoreSheet, WinnerSide } from '@/types/debate';

const props = defineProps<{
    matchId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Perlawanan Saya',
                href: debate.judge.matches.index().url,
            },
            {
                title: 'Pemarkahan Perlawanan',
                href: '#',
            },
        ],
    },
});

const http = useHttp();
const page = usePage();
const userId = page.props.auth.user.id;
const matchData = ref<Match | null>(null);
const scoreSheet = ref<ScoreSheet | null>(null);
const loading = ref(true);
const isSubmitFinalDialogOpen = ref(false);

const fetchMatchAndScores = async () => {
    loading.value = true;

    try {
        const [mRes, sRes] = await Promise.all([
            http.get(judge.matches.show(props.matchId).url),
            http.get(judge.matches.scoreSheet.show(props.matchId).url),
        ]);
        matchData.value = unwrapData<Match>(mRes);
        scoreSheet.value = unwrapData<ScoreSheet>(sRes);

        if (scoreSheet.value) {
            scoreForm.mark_pm = Number(scoreSheet.value.mark_pm);
            scoreForm.mark_tpm = Number(scoreSheet.value.mark_tpm);
            scoreForm.mark_m1 = Number(scoreSheet.value.mark_m1);
            scoreForm.mark_kp = Number(scoreSheet.value.mark_kp);
            scoreForm.mark_tkp = Number(scoreSheet.value.mark_tkp);
            scoreForm.mark_p1 = Number(scoreSheet.value.mark_p1);
            scoreForm.mark_penggulungan_gov = Number(scoreSheet.value.mark_penggulungan_gov);
            scoreForm.mark_penggulungan_opp = Number(scoreSheet.value.mark_penggulungan_opp);
            scoreForm.margin = Number(scoreSheet.value.margin);
            scoreForm.best_debater_member_id = scoreSheet.value.best_debater_member_id || null;
        }
    } catch (error) {
        matchData.value = null;
        scoreSheet.value = null;
        console.error('Failed to load match scoring data', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchMatchAndScores);

const myAssignment = computed(() => {
    return matchData.value?.judge_assignments?.find((assignment) => assignment.judge_id === userId);
});

const scoreForm = useHttp({
    mark_pm: 0,
    mark_tpm: 0,
    mark_m1: 0,
    mark_kp: 0,
    mark_tkp: 0,
    mark_p1: 0,
    mark_penggulungan_gov: 0,
    mark_penggulungan_opp: 0,
    margin: 0,
    best_debater_member_id: null as number | null,
});

const totals = computed(() => {
    const gov = Number(scoreForm.mark_pm) + Number(scoreForm.mark_tpm) + Number(scoreForm.mark_m1) + Number(scoreForm.mark_penggulungan_gov);
    const opp = Number(scoreForm.mark_kp) + Number(scoreForm.mark_tkp) + Number(scoreForm.mark_p1) + Number(scoreForm.mark_penggulungan_opp);
    const winner: WinnerSide = gov > opp ? 'government' : 'opposition';

    return { gov, opp, winner };
});

const calculatedMargin = computed(() => {
    return Math.abs(totals.value.gov - totals.value.opp);
});

watchEffect(() => {
    scoreForm.margin = Number(calculatedMargin.value.toFixed(1));
});

const checkIn = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        const checkInHttp = useHttp({});
        await checkInHttp.post(judge.matches.checkIn(matchData.value.id).url);
        fetchMatchAndScores();
    } catch (error) {
        console.error('Check-in failed', error);
    }
};

const saveDraft = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        await scoreForm.put(judge.matches.scoreSheet.draft(matchData.value.id).url);
        await fetchMatchAndScores();
        toast.success('Draf markah berjaya disimpan.');
    } catch (error) {
        toast.error('Gagal menyimpan draf markah. Sila cuba lagi.');
        console.error('Save draft failed', error);
    }
};

const submitFinal = async () => {
    if (!matchData.value) {
        return;
    }

    try {
        await scoreForm.post(judge.matches.scoreSheet.submit(matchData.value.id).url);
        isSubmitFinalDialogOpen.value = false;
        await fetchMatchAndScores();
        toast.success('Markah muktamad berjaya dihantar.');
    } catch (error) {
        toast.error('Gagal menghantar markah muktamad. Sila cuba lagi.');
        console.error('Submit failed', error);
    }
};

const allMembers = computed(() => {
    if (!matchData.value) {
        return [];
    }

    return [
        ...(matchData.value.government_team?.members ?? []),
        ...(matchData.value.opposition_team?.members ?? []),
    ];
});

const eligibleBestDebaters = computed(() => {
    return scoredMembers(allMembers.value);
});

const governmentLineup = computed(() => sortMembersBySpeakerPosition(matchData.value?.government_team?.members));
const oppositionLineup = computed(() => sortMembersBySpeakerPosition(matchData.value?.opposition_team?.members));

const isLocked = computed(() => {
    return matchData.value?.status === 'completed' || scoreSheet.value?.state === 'submitted';
});
</script>

<template>
    <Head title="Pemarkahan Perlawanan" />

    <div class="p-6 space-y-6">
        <div v-if="!matchData && loading" class="text-sm text-muted-foreground">Memuatkan perlawanan...</div>
        <template v-else-if="matchData">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" as-child>
                    <Link :href="debate.judge.matches.index()">
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-semibold">Pemarkahan Perlawanan</h1>
                    <p class="text-sm text-muted-foreground">{{ matchData.round?.name }} • {{ matchData.room?.name }}</p>
                </div>
            </div>

            <div v-if="myAssignment && !myAssignment.checked_in_at">
                <Button @click="checkIn">
                    <CheckCircle2 class="w-4 h-4 mr-2" />
                    Daftar Hadir
                </Button>
            </div>
            <div v-else-if="!isLocked" class="flex gap-2">
                <Button variant="outline" @click="saveDraft">
                    <Save class="w-4 h-4 mr-2" />
                    Simpan Draf
                </Button>
                <Button @click="isSubmitFinalDialogOpen = true">
                    <Send class="w-4 h-4 mr-2" />
                    Hantar Muktamad
                </Button>
            </div>
            <div v-else-if="scoreSheet?.state === 'submitted'">
                <Badge variant="success" class="px-4 py-1 text-sm">
                    <CheckCircle2 class="w-4 h-4 mr-2" />
                    Markah Muktamad Dihantar
                </Badge>
            </div>
        </div>

        <div v-if="myAssignment && !myAssignment.checked_in_at" class="flex flex-col items-center justify-center py-20 bg-muted/30 rounded-xl border-2 border-dashed">
            <Shield class="w-16 h-16 text-muted-foreground/30 mb-4" />
            <h2 class="text-xl font-bold">Menunggu Daftar Hadir</h2>
            <p class="text-muted-foreground mt-2 max-w-sm text-center">Sila daftar hadir apabila anda berada di bilik perlawanan untuk mula memberi markah.</p>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-4">
            <!-- Summary Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Maklumat Perlawanan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase text-muted-foreground">Kerajaan</Label>
                            <p class="font-bold text-primary">{{ matchData.government_team?.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase text-muted-foreground">Pembangkang</Label>
                            <p class="font-bold text-destructive">{{ matchData.opposition_team?.name }}</p>
                        </div>
                        <div class="pt-4 border-t space-y-4">
                            <div class="flex justify-between items-end">
                                <span class="text-xs text-muted-foreground">Jumlah Kerajaan</span>
                                <span class="text-2xl font-black text-primary">{{ totals.gov.toFixed(1) }}</span>
                            </div>
                            <div class="flex justify-between items-end">
                                <span class="text-xs text-muted-foreground">Jumlah Pembangkang</span>
                                <span class="text-2xl font-black text-destructive">{{ totals.opp.toFixed(1) }}</span>
                            </div>
                            <div class="pt-2 flex justify-between items-center border-t">
                                <span class="text-xs font-bold uppercase">Pemenang</span>
                                <Badge :variant="totals.winner === 'government' ? 'default' : 'destructive'">
                                    {{ totals.winner === 'government' ? 'Kerajaan' : 'Pembangkang' }}
                                </Badge>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase">Margin</span>
                                <Input
                                    v-model="scoreForm.margin"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    readonly
                                    class="h-9 w-28 text-right font-bold"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="isLocked && matchData.result" class="border-amber-200 bg-amber-50 dark:bg-amber-950/20">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4" />
                            Perlawanan Dimuktamadkan
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs">
                        Perlawanan ini telah selesai. Pemarkahan tidak lagi dibenarkan.
                    </CardContent>
                </Card>
            </div>

            <!-- Scoring Form -->
            <div class="lg:col-span-3 space-y-6">
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Government Side -->
                    <Card class="border-primary/20">
                        <CardHeader class="bg-primary/5 pb-4">
                            <CardTitle class="text-primary">Bahagian Kerajaan</CardTitle>
                        </CardHeader>
                        <CardContent class="p-6 space-y-6">
                            <div class="space-y-4">
                                <div class="grid grid-cols-4 items-center gap-4">
                                    <Label class="col-span-3">{{ speakerRoleLabel('speaker_1', 'government') }}<span v-if="governmentLineup[0]"> - {{ governmentLineup[0].full_name }}</span></Label>
                                    <Input v-model="scoreForm.mark_pm" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                                <div class="grid grid-cols-4 items-center gap-4">
                                    <Label class="col-span-3">{{ speakerRoleLabel('speaker_2', 'government') }}<span v-if="governmentLineup[1]"> - {{ governmentLineup[1].full_name }}</span></Label>
                                    <Input v-model="scoreForm.mark_tpm" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                                <div class="grid grid-cols-4 items-center gap-4">
                                    <Label class="col-span-3">{{ speakerRoleLabel('speaker_3', 'government') }}<span v-if="governmentLineup[2]"> - {{ governmentLineup[2].full_name }}</span></Label>
                                    <Input v-model="scoreForm.mark_m1" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                                <div class="grid grid-cols-4 items-center gap-4 pt-4 border-t">
                                    <Label class="col-span-3 font-bold">Penggulungan Kerajaan</Label>
                                    <Input v-model="scoreForm.mark_penggulungan_gov" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Opposition Side -->
                    <Card class="border-destructive/20">
                        <CardHeader class="bg-destructive/5 pb-4">
                            <CardTitle class="text-destructive">Bahagian Pembangkang</CardTitle>
                        </CardHeader>
                        <CardContent class="p-6 space-y-6">
                            <div class="space-y-4">
                                <div class="grid grid-cols-4 items-center gap-4">
                                    <Label class="col-span-3">{{ speakerRoleLabel('speaker_1', 'opposition') }}<span v-if="oppositionLineup[0]"> - {{ oppositionLineup[0].full_name }}</span></Label>
                                    <Input v-model="scoreForm.mark_kp" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                                <div class="grid grid-cols-4 items-center gap-4">
                                    <Label class="col-span-3">{{ speakerRoleLabel('speaker_2', 'opposition') }}<span v-if="oppositionLineup[1]"> - {{ oppositionLineup[1].full_name }}</span></Label>
                                    <Input v-model="scoreForm.mark_tkp" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                                <div class="grid grid-cols-4 items-center gap-4">
                                    <Label class="col-span-3">{{ speakerRoleLabel('speaker_3', 'opposition') }}<span v-if="oppositionLineup[2]"> - {{ oppositionLineup[2].full_name }}</span></Label>
                                    <Input v-model="scoreForm.mark_p1" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                                <div class="grid grid-cols-4 items-center gap-4 pt-4 border-t">
                                    <Label class="col-span-3 font-bold">Penggulungan Pembangkang</Label>
                                    <Input v-model="scoreForm.mark_penggulungan_opp" type="number" step="0.5" :disabled="isLocked" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Best Debater Selection -->
                <Card>
                    <CardHeader>
                        <CardTitle>Pendebat Terbaik</CardTitle>
                        <CardDescription>Pilih pendebat terbaik daripada enam pendebat utama.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Select v-model="scoreForm.best_debater_member_id" :disabled="isLocked">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih pendebat terbaik" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="member in eligibleBestDebaters" :key="member.id" :value="member.id">
                                    {{ member.full_name }} ({{ speakerRoleLabel(member.speaker_position, member.team_id === matchData.government_team_id ? 'government' : 'opposition') }}) - {{ member.team_id === matchData.government_team_id ? 'Kerajaan' : 'Pembangkang' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>
            </div>
        </div>
        </template>

        <Dialog :open="isSubmitFinalDialogOpen" @update:open="isSubmitFinalDialogOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Sahkan Hantar Muktamad</DialogTitle>
                    <DialogDescription>
                        Adakah anda pasti mahu menghantar borang markah ini? Markah akan dikunci selepas dihantar.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isSubmitFinalDialogOpen = false">
                        Batal
                    </Button>
                    <Button @click="submitFinal">
                        Ya, Hantar Muktamad
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
