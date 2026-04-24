<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Eye, Hourglass, MapPin, Plus, Shuffle, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { unwrapCollection } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import type { Match, Round, Room, Team, TeamMember } from '@/types/debate';

type LineupSide = {
    speaker_1: number | null;
    speaker_2: number | null;
    speaker_3: number | null;
    speaker_4: number | null;
};
type LineupPosition = keyof LineupSide;
type LineupSideKey = 'government' | 'opposition';
type RandomizeResponse = {
    data?: {
        unpaired_team?: {
            name?: string;
        } | null;
    };
};

const emptyLineupSide = (): LineupSide => ({
    speaker_1: null,
    speaker_2: null,
    speaker_3: null,
    speaker_4: null,
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Perlawanan',
                href: debate.admin.matches.index().url,
            },
        ],
    },
});

const http = useHttp();
const mutationHttp = useHttp({
    round_id: null as number | null,
    room_id: null as number | null,
    government_team_id: null as number | null,
    opposition_team_id: null as number | null,
    judge_panel_size: 3 as number,
    scheduled_at: '',
    government: emptyLineupSide(),
    opposition: emptyLineupSide(),
});
const matches = ref<Match[]>([]);
const rounds = ref<Round[]>([]);
const rooms = ref<Room[]>([]);
const teams = ref<Team[]>([]);
const loading = ref(true);
const randomizingFirstRound = ref(false);
const isRandomizeDialogOpen = ref(false);

const fetchData = async () => {
    loading.value = true;

    try {
        const [mRes, rRes, rmRes, tRes] = await Promise.all([
            http.get(admin.matches.index().url),
            http.get(admin.rounds.index().url),
            http.get(admin.rooms.index().url),
            http.get(admin.teams.index().url),
        ]);
        matches.value = unwrapCollection<Match>(mRes);
        rounds.value = unwrapCollection<Round>(rRes);
        rooms.value = unwrapCollection<Room>(rmRes);
        teams.value = unwrapCollection<Team>(tRes);
    } catch (error) {
        matches.value = [];
        rounds.value = [];
        rooms.value = [];
        teams.value = [];
        console.error('Failed to load match form data', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);

const isDialogOpen = ref(false);
const editingMatch = ref<Match | null>(null);

const assignedTeamIdsInSelectedRound = computed(() => {
    const selectedRoundId = mutationHttp.round_id;

    if (!selectedRoundId) {
        return new Set<number>();
    }

    const assignedIds = matches.value
        .filter((match) => match.round_id === selectedRoundId)
        .flatMap((match) => [match.government_team_id, match.opposition_team_id]);

    return new Set<number>(assignedIds);
});

const assignedRoomIdsInSelectedRound = computed(() => {
    const selectedRoundId = mutationHttp.round_id;

    if (!selectedRoundId) {
        return new Set<number>();
    }

    const assignedIds = matches.value
        .filter((match) => match.round_id === selectedRoundId)
        .map((match) => match.room_id);

    return new Set<number>(assignedIds);
});

const availableTeams = computed(() => {
    return teams.value.filter((team) => !assignedTeamIdsInSelectedRound.value.has(team.id));
});

const availableRooms = computed(() => {
    return rooms.value.filter((room) => !assignedRoomIdsInSelectedRound.value.has(room.id));
});

const firstRound = computed(() => {
    return [...rounds.value].sort((a, b) => (a.sequence ?? Number.MAX_SAFE_INTEGER) - (b.sequence ?? Number.MAX_SAFE_INTEGER))[0] ?? null;
});

const firstRoundMatches = computed(() => {
    if (!firstRound.value) {
        return [];
    }

    return matches.value.filter((match) => match.round_id === firstRound.value?.id);
});

const canShuffleFirstRound = computed(() => {
    return Boolean(firstRound.value) && !firstRoundMatches.value.some((match) => match.status !== 'pending');
});

const openCreateDialog = () => {
    editingMatch.value = null;
    mutationHttp.round_id = rounds.value[0]?.id || null;
    mutationHttp.room_id = availableRooms.value[0]?.id || null;
    mutationHttp.government_team_id = null;
    mutationHttp.opposition_team_id = null;
    mutationHttp.judge_panel_size = 3;
    mutationHttp.scheduled_at = new Date().toISOString().slice(0, 16);
    mutationHttp.government = emptyLineupSide();
    mutationHttp.opposition = emptyLineupSide();
    isDialogOpen.value = true;
};

const teamById = (teamId: number | null): Team | undefined => {
    return teams.value.find((team) => team.id === teamId);
};

const defaultLineupFromMembers = (members: TeamMember[] = []): LineupSide => {
    return {
        speaker_1: members.find((member) => member.speaker_position === 'speaker_1')?.id ?? null,
        speaker_2: members.find((member) => member.speaker_position === 'speaker_2')?.id ?? null,
        speaker_3: members.find((member) => member.speaker_position === 'speaker_3')?.id ?? null,
        speaker_4: members.find((member) => member.speaker_position === 'speaker_4')?.id ?? null,
    };
};

const selectedGovernmentTeam = computed(() => teamById(mutationHttp.government_team_id));
const selectedOppositionTeam = computed(() => teamById(mutationHttp.opposition_team_id));

const governmentRosterOptions = computed(() => selectedGovernmentTeam.value?.members ?? []);
const oppositionRosterOptions = computed(() => selectedOppositionTeam.value?.members ?? []);
const lineupPositions: LineupPosition[] = ['speaker_1', 'speaker_2', 'speaker_3', 'speaker_4'];
let syncingLineupSwap = false;

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

    const side = mutationHttp[sideKey];
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

const saveMatch = async () => {
    try {
        if (editingMatch.value) {
            await mutationHttp.patch(admin.matches.update(editingMatch.value.id).url);
        } else {
            await mutationHttp.post(admin.matches.store().url);
        }

        isDialogOpen.value = false;
        fetchData();
    } catch (error) {
        console.error('Failed to save match', error);
    }
};

const deleteMatch = async (match: Match) => {
    if (!confirm('Adakah anda pasti mahu memadam perlawanan ini?')) {
        return;
    }

    try {
        await http.delete(admin.matches.destroy(match.id).url);
        fetchData();
    } catch (error) {
        console.error('Failed to delete match', error);
    }
};

const errorMessageFromResponse = (error: unknown, fallback: string): string => {
    const maybeError = error as {
        response?: {
            data?: string | {
                message?: string;
            };
        };
        message?: string;
    };

    if (typeof maybeError.response?.data === 'string') {
        try {
            const parsed = JSON.parse(maybeError.response.data) as { message?: string };

            return parsed.message ?? fallback;
        } catch {
            return fallback;
        }
    }

    return maybeError.response?.data?.message ?? maybeError.message ?? fallback;
};

const openRandomizeDialog = () => {
    if (!firstRound.value) {
        toast.error('Cipta pusingan terlebih dahulu sebelum jana matchup.');

        return;
    }

    isRandomizeDialogOpen.value = true;
};

const setRandomizeDialogOpen = (isOpen: boolean) => {
    if (randomizingFirstRound.value) {
        return;
    }

    isRandomizeDialogOpen.value = isOpen;
};

const wait = (milliseconds: number): Promise<void> => {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
};

const randomizeFirstRound = async () => {
    if (!firstRound.value) {
        toast.error('Cipta pusingan terlebih dahulu sebelum jana matchup.');

        return;
    }

    randomizingFirstRound.value = true;

    try {
        const [response] = await Promise.all([
            http.post(admin.rounds.matches.randomize(firstRound.value.id).url) as Promise<RandomizeResponse>,
            wait(3000),
        ]);

        await fetchData();
        isRandomizeDialogOpen.value = false;

        const unpairedTeamName = response?.data?.unpaired_team?.name;
        const message = unpairedTeamName
            ? `Matchup pusingan 1 berjaya dijana. ${unpairedTeamName} tidak dipadankan kerana jumlah pasukan ganjil.`
            : 'Matchup pusingan 1 berjaya dijana.';

        toast.success(message);
    } catch (error) {
        toast.error(errorMessageFromResponse(error, 'Gagal menjana matchup rawak pusingan 1.'));
        console.error('Failed to randomize first round matchups', error);
    } finally {
        randomizingFirstRound.value = false;
    }
};

watch(
    () => mutationHttp.round_id,
    () => {
        if (mutationHttp.room_id && assignedRoomIdsInSelectedRound.value.has(mutationHttp.room_id)) {
            mutationHttp.room_id = null;
        }

        if (!mutationHttp.room_id) {
            mutationHttp.room_id = availableRooms.value[0]?.id ?? null;
        }

        if (mutationHttp.government_team_id && assignedTeamIdsInSelectedRound.value.has(mutationHttp.government_team_id)) {
            mutationHttp.government_team_id = null;
        }

        if (mutationHttp.opposition_team_id && assignedTeamIdsInSelectedRound.value.has(mutationHttp.opposition_team_id)) {
            mutationHttp.opposition_team_id = null;
        }
    },
);

watch(
    () => mutationHttp.government_team_id,
    () => {
        mutationHttp.government = defaultLineupFromMembers(selectedGovernmentTeam.value?.members);
    },
);

watch(
    () => mutationHttp.opposition_team_id,
    () => {
        mutationHttp.opposition = defaultLineupFromMembers(selectedOppositionTeam.value?.members);
    },
);

for (const position of lineupPositions) {
    watch(
        () => mutationHttp.government[position],
        (newValue, oldValue) => {
            syncLineupSwap('government', position, newValue, oldValue);
        },
    );

    watch(
        () => mutationHttp.opposition[position],
        (newValue, oldValue) => {
            syncLineupSwap('opposition', position, newValue, oldValue);
        },
    );
}

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
    <Head title="Perlawanan" />

    <div class="p-6 space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <Heading title="Perlawanan" description="Jadualkan dan urus perlawanan debat." />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" @click="openRandomizeDialog" :disabled="loading || randomizingFirstRound || !canShuffleFirstRound">
                    <Shuffle class="w-4 h-4 mr-2" />
                    {{ randomizingFirstRound ? 'Menjana...' : firstRoundMatches.length > 0 ? 'Shuffle Semula Pusingan 1' : 'Rawak Pusingan 1' }}
                </Button>
                <Button @click="openCreateDialog">
                    <Plus class="w-4 h-4 mr-2" />
                    Cipta Perlawanan
                </Button>
            </div>
        </div>

        <div id="generator-rawak" class="rounded-xl border bg-muted/20 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold">Generator Rawak Pusingan 1</h2>
                    <p class="text-sm text-muted-foreground">
                        Shuffle akan memadam matchup lama pusingan 1 dan menjana padanan baharu daripada pasukan serta bilik aktif.
                    </p>
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ firstRoundMatches.length }} matchup pusingan 1 sedia ada
                </div>
            </div>
            <p v-if="!canShuffleFirstRound && firstRoundMatches.length > 0" class="mt-3 text-sm text-destructive">
                Shuffle dikunci kerana ada perlawanan pusingan 1 yang sudah bermula atau selesai.
            </p>
        </div>

        <div class="relative w-full overflow-auto rounded-xl border bg-background">
            <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Pusingan</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Padanan (Kerajaan vs Pembangkang)</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Bilik</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <template v-if="loading">
                                <tr v-for="i in 3" :key="i" class="border-b transition-colors">
                                    <td class="p-4 align-middle"><div class="h-4 w-20 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-48 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-24 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-16 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted ml-auto"></div></td>
                                </tr>
                            </template>
                            <tr v-else-if="matches.length === 0" class="border-b transition-colors">
                                <td colspan="5" class="p-8 text-center text-muted-foreground">
                                    Belum ada perlawanan dijadualkan.
                                </td>
                            </tr>
                            <tr v-for="match in matches" :key="match.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle">{{ match.round?.name }}</td>
                                <td class="p-4 align-middle font-medium">
                                    <span class="text-primary">{{ match.government_team?.name }}</span>
                                    <span class="mx-2 text-muted-foreground text-xs font-normal">lwn</span>
                                    <span class="text-destructive">{{ match.opposition_team?.name }}</span>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="flex items-center text-xs text-muted-foreground">
                                        <MapPin class="w-3 h-3 mr-1" />
                                        {{ match.room?.name }}
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <Badge :variant="getStatusVariant(match.status)">
                                        {{ match.status === 'pending' ? 'Belum Bermula' : match.status === 'in_progress' ? 'Sedang Berjalan' : 'Selesai' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="debate.admin.matches.show(match.id)">
                                                <Eye class="w-4 h-4 mr-2" />
                                                Lihat
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="deleteMatch(match)"
                                        >
                                            <Trash2 class="w-4 h-4 mr-2" />
                                            Padam
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
            </table>
        </div>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Cipta Perlawanan</DialogTitle>
                    <DialogDescription>
                        Jadualkan satu perlawanan debat baharu antara dua pasukan.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-4 py-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Pusingan</Label>
                        <Select v-model="mutationHttp.round_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih pusingan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="round in rounds" :key="round.id" :value="round.id">
                                    {{ round.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Bilik</Label>
                        <Select v-model="mutationHttp.room_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih bilik" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="room in availableRooms" :key="room.id" :value="room.id">
                                    {{ room.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label>Pasukan Kerajaan</Label>
                        <Select v-model="mutationHttp.government_team_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih pasukan kerajaan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="team in availableTeams" :key="team.id" :value="team.id" :disabled="team.id === mutationHttp.opposition_team_id">
                                    {{ team.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label>Pasukan Pembangkang</Label>
                        <Select v-model="mutationHttp.opposition_team_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih pasukan pembangkang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="team in availableTeams" :key="team.id" :value="team.id" :disabled="team.id === mutationHttp.government_team_id">
                                    {{ team.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        v-if="selectedGovernmentTeam && selectedOppositionTeam"
                        class="grid gap-4 rounded-lg border border-dashed p-4 sm:col-span-2"
                    >
                        <div>
                            <h3 class="text-sm font-semibold">Lineup Perlawanan</h3>
                            <p class="text-xs text-muted-foreground">
                                Susun semula speaker untuk perlawanan ini jika perlu. Jika dibiarkan seperti asal, sistem akan guna susunan pasukan sedia ada.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid min-w-0 gap-3">
                                <Label class="text-xs font-bold uppercase tracking-wider text-primary">Kerajaan</Label>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="government-speaker-1">Pendebat 1</Label>
                                    <Select v-model="mutationHttp.government.speaker_1">
                                        <SelectTrigger id="government-speaker-1" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih pendebat 1" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in governmentRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="government-speaker-2">Pendebat 2</Label>
                                    <Select v-model="mutationHttp.government.speaker_2">
                                        <SelectTrigger id="government-speaker-2" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih pendebat 2" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in governmentRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="government-speaker-3">Pendebat 3</Label>
                                    <Select v-model="mutationHttp.government.speaker_3">
                                        <SelectTrigger id="government-speaker-3" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih pendebat 3" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in governmentRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="government-speaker-4">Simpanan</Label>
                                    <Select v-model="mutationHttp.government.speaker_4">
                                        <SelectTrigger id="government-speaker-4" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih simpanan" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in governmentRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="grid min-w-0 gap-3">
                                <Label class="text-xs font-bold uppercase tracking-wider text-destructive">Pembangkang</Label>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="opposition-speaker-1">Pendebat 1</Label>
                                    <Select v-model="mutationHttp.opposition.speaker_1">
                                        <SelectTrigger id="opposition-speaker-1" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih pendebat 1" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in oppositionRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="opposition-speaker-2">Pendebat 2</Label>
                                    <Select v-model="mutationHttp.opposition.speaker_2">
                                        <SelectTrigger id="opposition-speaker-2" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih pendebat 2" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in oppositionRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="opposition-speaker-3">Pendebat 3</Label>
                                    <Select v-model="mutationHttp.opposition.speaker_3">
                                        <SelectTrigger id="opposition-speaker-3" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih pendebat 3" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in oppositionRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid min-w-0 gap-2">
                                    <Label for="opposition-speaker-4">Simpanan</Label>
                                    <Select v-model="mutationHttp.opposition.speaker_4">
                                        <SelectTrigger id="opposition-speaker-4" class="w-full max-w-full min-w-0">
                                            <SelectValue placeholder="Pilih simpanan" class="truncate" />
                                        </SelectTrigger>
                                        <SelectContent class="max-w-[var(--reka-select-trigger-width)]">
                                            <SelectItem v-for="member in oppositionRosterOptions" :key="member.id" :value="member.id">
                                                <span class="block w-full truncate">{{ member.full_name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Saiz Panel</Label>
                        <Select v-model="mutationHttp.judge_panel_size">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih saiz" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="1">1 Hakim</SelectItem>
                                <SelectItem :value="3">3 Hakim</SelectItem>
                                <SelectItem :value="5">5 Hakim</SelectItem>
                                <SelectItem :value="7">7 Hakim</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Masa Dijadualkan</Label>
                        <Input v-model="mutationHttp.scheduled_at" type="datetime-local" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isDialogOpen = false">Batal</Button>
                    <Button @click="saveMatch">Cipta Perlawanan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="isRandomizeDialogOpen" @update:open="setRandomizeDialogOpen">
            <DialogContent class="sm:max-w-[440px]" :show-close-button="!randomizingFirstRound">
                <template v-if="randomizingFirstRound">
                    <div class="flex flex-col items-center gap-4 py-6 text-center">
                        <div class="flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Hourglass class="size-8 motion-safe:animate-spin" />
                        </div>
                        <div class="space-y-2">
                            <DialogTitle>Sedang shuffle matchup...</DialogTitle>
                            <DialogDescription>
                                Sistem sedang menjana padanan rawak pusingan 1. Tunggu sebentar supaya proses selesai dengan kemas.
                            </DialogDescription>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full w-full animate-pulse rounded-full bg-primary"></div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <DialogHeader>
                        <DialogTitle>
                            {{ firstRoundMatches.length > 0 ? 'Shuffle semula pusingan 1?' : 'Rawak pusingan 1?' }}
                        </DialogTitle>
                        <DialogDescription>
                            <span v-if="firstRoundMatches.length > 0">
                                Matchup lama pusingan 1 akan dipadam dan diganti dengan padanan rawak baharu.
                            </span>
                            <span v-else>
                                Sistem akan menjana matchup rawak untuk pusingan 1 menggunakan pasukan dan bilik aktif.
                            </span>
                        </DialogDescription>
                    </DialogHeader>

                    <div class="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
                        Tindakan ini hanya dibenarkan selagi semua perlawanan pusingan 1 masih belum bermula.
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isRandomizeDialogOpen = false">
                            Batal
                        </Button>
                        <Button @click="randomizeFirstRound">
                            <Shuffle class="mr-2 size-4" />
                            {{ firstRoundMatches.length > 0 ? 'Shuffle sekarang' : 'Rawak sekarang' }}
                        </Button>
                    </DialogFooter>
                </template>
            </DialogContent>
        </Dialog>
    </div>
</template>
