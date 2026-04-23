<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Plus, Edit2, Trash2, Eye } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
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
import { unwrapCollection } from '@/lib/httpPayload';
import admin from '@/routes/admin';
import debate from '@/routes/debate';
import type { Team } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pasukan',
                href: debate.admin.teams.index().url,
            },
        ],
    },
});

const http = useHttp();
const mutationHttp = useHttp({
    name: '',
    institution: '',
    is_active: true,
});
const teams = ref<Team[]>([]);
const loading = ref(true);

const fetchTeams = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.teams.index().url);
        teams.value = unwrapCollection<Team>(response);
    } catch (error) {
        teams.value = [];
        console.error('Failed to load teams', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchTeams);

const isDialogOpen = ref(false);
const editingTeam = ref<Team | null>(null);

const openCreateDialog = () => {
    editingTeam.value = null;
    mutationHttp.name = '';
    mutationHttp.institution = '';
    mutationHttp.is_active = true;
    isDialogOpen.value = true;
};

const openEditDialog = (team: Team) => {
    editingTeam.value = team;
    mutationHttp.name = team.name;
    mutationHttp.institution = team.institution || '';
    mutationHttp.is_active = team.is_active;
    isDialogOpen.value = true;
};

const saveTeam = async () => {
    try {
        if (editingTeam.value) {
            await mutationHttp.patch(admin.teams.update(editingTeam.value.id).url);
        } else {
            await mutationHttp.post(admin.teams.store().url);
        }

        isDialogOpen.value = false;
        fetchTeams();
    } catch (error) {
        console.error('Failed to save team', error);
    }
};

const deleteTeam = async (team: Team) => {
    if (!confirm('Adakah anda pasti mahu memadam pasukan ini?')) {
        return;
    }

    try {
        await http.delete(admin.teams.destroy(team.id).url);
        fetchTeams();
    } catch (error) {
        console.error('Failed to delete team', error);
    }
};
</script>

<template>
    <Head title="Pasukan" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Pasukan" description="Urus pasukan debat dan institusi masing-masing." />
            <Button @click="openCreateDialog">
                <Plus class="w-4 h-4 mr-2" />
                Tambah Pasukan
            </Button>
        </div>

        <div class="relative w-full overflow-auto rounded-xl border bg-background">
            <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Nama</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Institusi</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <template v-if="loading">
                                <tr v-for="i in 3" :key="i" class="border-b transition-colors">
                                    <td class="p-4 align-middle"><div class="h-4 w-24 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-32 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-16 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted ml-auto"></div></td>
                                </tr>
                            </template>
                            <tr v-else-if="teams.length === 0" class="border-b transition-colors">
                                <td colspan="4" class="p-8 text-center text-muted-foreground">
                                    Belum ada pasukan. Tambah pasukan pertama anda.
                                </td>
                            </tr>
                            <tr v-for="team in teams" :key="team.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle font-medium">{{ team.name }}</td>
                                <td class="p-4 align-middle">{{ team.institution || '-' }}</td>
                                <td class="p-4 align-middle">
                                    <Badge :variant="team.is_active ? 'default' : 'secondary'">
                                        {{ team.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="debate.admin.teams.show(team.id)">
                                                <Eye class="w-4 h-4 mr-2" />
                                                Lihat
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" @click="openEditDialog(team)">
                                            <Edit2 class="w-4 h-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" @click="deleteTeam(team)">
                                            <Trash2 class="w-4 h-4 text-destructive" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
            </table>
        </div>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editingTeam ? 'Sunting Pasukan' : 'Tambah Pasukan' }}</DialogTitle>
                    <DialogDescription>
                        Tetapkan nama pasukan dan institusi.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name">Nama Pasukan</Label>
                        <Input id="name" v-model="mutationHttp.name" placeholder="Pasukan A" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="institution">Institusi</Label>
                        <Input id="institution" v-model="mutationHttp.institution" placeholder="Universiti X" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isDialogOpen = false">Batal</Button>
                    <Button @click="saveTeam">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
