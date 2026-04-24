<script setup lang="ts">
import { Head, Link, useHttp, usePage } from '@inertiajs/vue3';
import { AlertCircle, CalendarDays, CheckCircle2, ClipboardCheck, Clock3, MapPin, Shield, Swords, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { unwrapCollection } from '@/lib/httpPayload';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import judge from '@/routes/judge';
import type { Match, Round, Room, Team, User } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const http = useHttp();
const page = usePage();

const user = computed(() => page.props.auth.user);
const currentUserId = computed(() => Number(user.value?.id ?? 0));
const isSuperadmin = computed(() => user.value?.role === 'superadmin');
const isJudge = computed(() => user.value?.role === 'judge');

const loading = ref(true);

const judges = ref<User[]>([]);
const rounds = ref<Round[]>([]);
const rooms = ref<Room[]>([]);
const teams = ref<Team[]>([]);
const superadminMatches = ref<Match[]>([]);

const judgeMatches = ref<Match[]>([]);

const fetchSuperadminDashboard = async (): Promise<void> => {
    loading.value = true;

    try {
        const [jRes, rRes, rmRes, tRes, mRes] = await Promise.all([
            http.get(admin.judges.index().url),
            http.get(admin.rounds.index().url),
            http.get(admin.rooms.index().url),
            http.get(admin.teams.index().url),
            http.get(admin.matches.index().url),
        ]);

        judges.value = unwrapCollection<User>(jRes);
        rounds.value = unwrapCollection<Round>(rRes);
        rooms.value = unwrapCollection<Room>(rmRes);
        teams.value = unwrapCollection<Team>(tRes);
        superadminMatches.value = unwrapCollection<Match>(mRes);
    } catch (error) {
        judges.value = [];
        rounds.value = [];
        rooms.value = [];
        teams.value = [];
        superadminMatches.value = [];
        console.error('Failed to load superadmin dashboard data', error);
    } finally {
        loading.value = false;
    }
};

const fetchJudgeDashboard = async (): Promise<void> => {
    loading.value = true;

    try {
        const response = await http.get(judge.matches.index().url);
        judgeMatches.value = unwrapCollection<Match>(response);
    } catch (error) {
        judgeMatches.value = [];
        console.error('Failed to load judge dashboard data', error);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    if (isSuperadmin.value) {
        await fetchSuperadminDashboard();

        return;
    }

    if (isJudge.value) {
        await fetchJudgeDashboard();

        return;
    }

    loading.value = false;
});

const activeJudgesCount = computed(() => judges.value.filter((item) => item.is_active).length);
const pendingMatchesCount = computed(() => superadminMatches.value.filter((match) => match.status === 'pending').length);
const inProgressMatchesCount = computed(() => superadminMatches.value.filter((match) => match.status === 'in_progress').length);
const completedMatchesCount = computed(() => superadminMatches.value.filter((match) => match.status === 'completed').length);

const unassignedMatches = computed(() => {
    return superadminMatches.value.filter((match) => (match.judge_assignments?.length ?? 0) === 0);
});

const unscheduledMatches = computed(() => {
    return superadminMatches.value.filter((match) => !match.scheduled_at);
});

const getJudgeAssignment = (match: Match) => {
    return match.judge_assignments?.find((assignment) => assignment.judge_id === currentUserId.value);
};

const judgeNeedsCheckInCount = computed(() => {
    return judgeMatches.value.filter((match) => {
        const assignment = getJudgeAssignment(match);

        return match.status !== 'completed' && !assignment?.checked_in_at;
    }).length;
});

const judgeNeedsSubmissionCount = computed(() => {
    return judgeMatches.value.filter((match) => {
        const assignment = getJudgeAssignment(match);

        return match.status !== 'completed' && !assignment?.submitted_at;
    }).length;
});

const judgeCompletedCount = computed(() => {
    return judgeMatches.value.filter((match) => {
        const assignment = getJudgeAssignment(match);

        return !!assignment?.submitted_at;
    }).length;
});

const judgeActionItems = computed(() => {
    return judgeMatches.value
        .filter((match) => {
            const assignment = getJudgeAssignment(match);

            return !assignment?.submitted_at;
        })
        .sort((left, right) => {
            const leftTime = left.scheduled_at ? new Date(left.scheduled_at).getTime() : Number.MAX_SAFE_INTEGER;
            const rightTime = right.scheduled_at ? new Date(right.scheduled_at).getTime() : Number.MAX_SAFE_INTEGER;

            return leftTime - rightTime;
        })
        .slice(0, 5);
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return 'Belum dijadualkan';
    }

    return new Date(value).toLocaleString();
};

</script>

<template>
    <Head title="Dashboard" />

    <div class="p-6 space-y-6">
        <template v-if="isSuperadmin">
            <Heading
                title="Dashboard Superadmin"
                description="Ringkasan operasi kejohanan untuk pemantauan hakim, jadual, dan kemajuan perlawanan."
            />

            <div v-if="loading" class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <Card v-for="index in 4" :key="index" class="animate-pulse">
                    <CardContent class="p-6">
                        <div class="h-4 w-24 rounded bg-muted"></div>
                        <div class="mt-3 h-8 w-14 rounded bg-muted"></div>
                    </CardContent>
                </Card>
            </div>

            <template v-else>
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Hakim Aktif</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ activeJudgesCount }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Shield class="h-3.5 w-3.5" />
                                {{ judges.length }} hakim berdaftar
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Pusingan</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ rounds.length }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <CalendarDays class="h-3.5 w-3.5" />
                                Struktur pertandingan semasa
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Bilik Aktif</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ rooms.filter((room) => room.is_active).length }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <MapPin class="h-3.5 w-3.5" />
                                {{ rooms.length }} bilik direkodkan
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Pasukan</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ teams.length }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Users class="h-3.5 w-3.5" />
                                Termasuk pasukan aktif dan simpanan
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Perlawanan Pending</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ pendingMatchesCount }}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Perlawanan Sedang Berjalan</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ inProgressMatchesCount }}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Perlawanan Selesai</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ completedMatchesCount }}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Akses Pantas</CardTitle>
                            <CardDescription>Modul terpenting untuk urusan harian superadmin.</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-3 sm:grid-cols-2">
                            <Button variant="outline" as-child>
                                <Link :href="debate.admin.judges.index()">Urus Hakim</Link>
                            </Button>
                            <Button variant="outline" as-child>
                                <Link :href="debate.admin.matches.index()">Urus Perlawanan</Link>
                            </Button>
                            <Button variant="outline" as-child>
                                <Link :href="debate.admin.rankings.teams()">Semak Kedudukan</Link>
                            </Button>
                            <Button variant="outline" as-child>
                                <Link :href="debate.admin.reports.tournament()">Laporan Kejohanan</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Perlu Tindakan Segera</CardTitle>
                            <CardDescription>Item operasi yang masih belum lengkap.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-start justify-between rounded-lg border p-3">
                                <div>
                                    <p class="text-sm font-medium">Perlawanan belum ada panel hakim</p>
                                    <p class="text-xs text-muted-foreground">Lengkapkan penugasan untuk elakkan kelewatan.</p>
                                </div>
                                <Badge variant="secondary">{{ unassignedMatches.length }}</Badge>
                            </div>

                            <div class="flex items-start justify-between rounded-lg border p-3">
                                <div>
                                    <p class="text-sm font-medium">Perlawanan belum dijadualkan</p>
                                    <p class="text-xs text-muted-foreground">Tetapkan masa supaya aliran pusingan jelas.</p>
                                </div>
                                <Badge variant="secondary">{{ unscheduledMatches.length }}</Badge>
                            </div>

                            <div v-if="unassignedMatches.length === 0 && unscheduledMatches.length === 0" class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                                <CheckCircle2 class="h-4 w-4" />
                                Tiada isu operasi kritikal buat masa ini.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </template>

        <template v-else-if="isJudge">
            <Heading
                title="Dashboard Hakim"
                description="Tumpuan kepada perlawanan tugasan anda, status semasa, dan tindakan yang perlu disiapkan."
            />

            <div v-if="loading" class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <Card v-for="index in 4" :key="index" class="animate-pulse">
                    <CardContent class="p-6">
                        <div class="h-4 w-24 rounded bg-muted"></div>
                        <div class="mt-3 h-8 w-14 rounded bg-muted"></div>
                    </CardContent>
                </Card>
            </div>

            <template v-else>
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Jumlah Tugasan</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ judgeMatches.length }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Swords class="h-3.5 w-3.5" />
                                Semua perlawanan yang diamanahkan
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Perlu Check-In</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ judgeNeedsCheckInCount }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Clock3 class="h-3.5 w-3.5" />
                                Sahkan kehadiran sebelum mula menilai
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Skor Belum Dihantar</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ judgeNeedsSubmissionCount }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <ClipboardCheck class="h-3.5 w-3.5" />
                                Lengkapkan borang skor bagi keputusan rasmi
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Tugasan Siap</CardDescription>
                            <CardTitle class="text-3xl font-black">{{ judgeCompletedCount }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Skor telah dihantar
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Senarai Tindakan</CardTitle>
                        <CardDescription>Perlawanan yang masih memerlukan semakan atau penghantaran skor.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-if="judgeActionItems.length === 0" class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                            <CheckCircle2 class="h-4 w-4" />
                            Semua tugasan anda sudah lengkap.
                        </div>

                        <div v-for="match in judgeActionItems" :key="match.id" class="flex flex-col gap-3 rounded-lg border p-4 md:flex-row md:items-center md:justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold">
                                    {{ match.government_team?.name }}
                                    <span class="mx-1 text-muted-foreground">lwn</span>
                                    {{ match.opposition_team?.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ match.round?.name }} · {{ match.room?.name }} · {{ formatDateTime(match.scheduled_at) }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <Badge v-if="!getJudgeAssignment(match)?.checked_in_at" variant="secondary">Belum Check-In</Badge>
                                <Badge v-if="!getJudgeAssignment(match)?.submitted_at" variant="secondary">Belum Hantar Skor</Badge>
                                <Button size="sm" as-child>
                                    <Link :href="debate.judge.matches.show(match.id)">Buka</Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Akses Pantas</CardTitle>
                        <CardDescription>Lompatan terus ke modul tugasan hakim.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" as-child>
                            <Link :href="debate.judge.matches.index()">Buka Perlawanan Saya</Link>
                        </Button>
                    </CardContent>
                </Card>
            </template>
        </template>

        <div v-else-if="!loading" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
            <div class="flex items-center gap-2">
                <AlertCircle class="h-4 w-4" />
                Peranan pengguna tidak disokong untuk paparan dashboard ini.
            </div>
        </div>
    </div>
</template>
