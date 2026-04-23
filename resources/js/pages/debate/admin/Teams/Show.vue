<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Plus, Edit2, Trash2, ArrowLeft } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { orderedSpeakerPositions, speakerPositionLabel } from '@/lib/debateSpeakers';
import { unwrapData } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import type { Team, TeamMember, SpeakerPosition } from '@/types/debate';

const props = defineProps<{
    teamId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pasukan',
                href: debate.admin.teams.index().url,
            },
            {
                title: 'Butiran Pasukan',
                href: '#',
            },
        ],
    },
});

const http = useHttp();
const teamData = ref<Team | null>(null);
const loading = ref(false);

const fetchTeam = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.teams.show(props.teamId).url);
        teamData.value = unwrapData<Team>(response);
    } catch (error) {
        teamData.value = null;
        console.error('Failed to load team details', error);
    } finally {
        loading.value = false;
    }
};

fetchTeam();

// Member Create/Edit State
const isMemberDialogOpen = ref(false);
const editingMember = ref<TeamMember | null>(null);
const memberHttp = useHttp({
    full_name: '',
    speaker_position: 'speaker_1' as SpeakerPosition,
    is_active: true,
});

const occupiedPositions = computed(() => new Set((teamData.value?.members ?? []).map((member) => member.speaker_position)));

const selectablePositions = computed(() => {
    return orderedSpeakerPositions.filter((position) => {
        return editingMember.value?.speaker_position === position || !occupiedPositions.value.has(position);
    });
});

const canAddMember = computed(() => selectablePositions.value.length > 0);

const defaultAvailablePosition = (): SpeakerPosition => {
    return selectablePositions.value[0] ?? 'speaker_1';
};

const openCreateMemberDialog = () => {
    editingMember.value = null;
    memberHttp.full_name = '';
    memberHttp.speaker_position = defaultAvailablePosition();
    memberHttp.is_active = true;
    isMemberDialogOpen.value = true;
};

const openEditMemberDialog = (member: TeamMember) => {
    editingMember.value = member;
    memberHttp.full_name = member.full_name;
    memberHttp.speaker_position = member.speaker_position;
    memberHttp.is_active = member.is_active;
    isMemberDialogOpen.value = true;
};

const saveMember = async () => {
    try {
        if (!teamData.value) {
            return;
        }

        if (editingMember.value) {
            await memberHttp.patch(admin.teams.members.update([teamData.value.id, editingMember.value.id]).url);
        } else {
            await memberHttp.post(admin.teams.members.store(teamData.value.id).url);
        }

        isMemberDialogOpen.value = false;
        fetchTeam();
    } catch (error) {
        console.error('Failed to save member', error);
    }
};

const deleteMember = async (member: TeamMember) => {
    if (!confirm('Adakah anda pasti mahu memadam ahli ini?')) {
        return;
    }

    try {
        if (!teamData.value) {
            return;
        }

        await http.delete(admin.teams.members.destroy([teamData.value.id, member.id]).url);
        fetchTeam();
    } catch (error) {
        console.error('Failed to delete member', error);
    }
};
</script>

<template>
    <Head :title="`Pasukan: ${teamData?.name ?? 'Butiran'}`" />

    <div class="p-6 space-y-6">
        <div v-if="!teamData && loading" class="text-sm text-muted-foreground">Memuatkan pasukan...</div>
        <template v-else-if="teamData">
        <div class="flex items-center gap-4">
            <Button variant="outline" size="icon" as-child>
                <Link :href="debate.admin.teams.index()">
                    <ArrowLeft class="w-4 h-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-semibold">{{ teamData.name }}</h1>
                <p class="text-sm text-muted-foreground">{{ teamData.institution }}</p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Maklumat Pasukan</CardTitle>
                    <CardDescription>Butiran umum pasukan debat.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-1">
                        <Label class="text-muted-foreground">Nama Pasukan</Label>
                        <p class="font-medium">{{ teamData.name }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-muted-foreground">Institusi</Label>
                        <p class="font-medium">{{ teamData.institution || '-' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-muted-foreground">Status</Label>
                        <div>
                            <Badge :variant="teamData.is_active ? 'default' : 'secondary'">
                                {{ teamData.is_active ? 'Aktif' : 'Tidak Aktif' }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <div class="flex flex-row items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Ahli Pasukan</h2>
                        <p class="text-sm text-muted-foreground">Tetapkan pendebat 1 hingga 4. Pendebat 4 sentiasa simpanan.</p>
                    </div>
                    <Button size="sm" @click="openCreateMemberDialog" :disabled="!canAddMember">
                        <Plus class="w-4 h-4 mr-2" />
                        Tambah Ahli
                    </Button>
                </div>
                <p v-if="!canAddMember" class="text-sm text-muted-foreground">
                    Semua slot pendebat telah diisi. Gunakan sunting untuk ubah susunan.
                </p>
                <div class="relative w-full overflow-auto rounded-xl border bg-background">
                    <table class="w-full text-sm">
                            <thead class="border-b">
                                <tr class="bg-muted/50">
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Nama</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Slot</th>
                                    <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!teamData.members || teamData.members.length === 0">
                                    <td colspan="3" class="p-4 text-center text-muted-foreground">Belum ada ahli pasukan.</td>
                                </tr>
                                <tr v-for="member in teamData.members" :key="member.id" class="border-b transition-colors hover:bg-muted/50">
                                    <td class="p-4 align-middle font-medium">{{ member.full_name }}</td>
                                    <td class="p-4 align-middle"><Badge variant="outline">{{ member.speaker_position_label ?? speakerPositionLabel(member.speaker_position) }}</Badge></td>
                                    <td class="p-4 align-middle text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" @click="openEditMemberDialog(member)">
                                                <Edit2 class="w-4 h-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" @click="deleteMember(member)">
                                                <Trash2 class="w-4 h-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Dialog v-model:open="isMemberDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editingMember ? 'Sunting Ahli' : 'Tambah Ahli' }}</DialogTitle>
                    <DialogDescription>
                        Isi maklumat ahli pasukan. Slot pendebat akan digunakan untuk tentukan peranan semasa perlawanan.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="full_name">Nama Penuh</Label>
                        <Input id="full_name" v-model="memberHttp.full_name" placeholder="Ahmad bin Ali" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="position">Slot Pendebat</Label>
                        <Select v-model="memberHttp.speaker_position">
                            <SelectTrigger id="position">
                                <SelectValue placeholder="Pilih slot pendebat" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="pos in selectablePositions" :key="pos" :value="pos">
                                    {{ speakerPositionLabel(pos) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Pendebat 1-3 akan bermain. Pendebat 4 ialah simpanan dan tidak dinilai dalam borang markah.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isMemberDialogOpen = false">Batal</Button>
                    <Button @click="saveMember">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        </template>
    </div>
</template>
