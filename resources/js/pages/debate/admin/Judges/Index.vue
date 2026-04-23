<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Plus, Edit2, Power, PowerOff } from 'lucide-vue-next';
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
import type { User } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Hakim',
                href: admin.judges.index().url,
            },
        ],
    },
});

const http = useHttp();
const mutationHttp = useHttp({
    name: '',
    email: '',
    password: '',
    is_active: true,
});
const judges = ref<User[]>([]);
const loading = ref(true);

const fetchJudges = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.judges.index().url);
        judges.value = unwrapCollection<User>(response);
    } catch (error) {
        judges.value = [];
        console.error('Failed to load judges', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchJudges);

const isDialogOpen = ref(false);
const editingJudge = ref<User | null>(null);

const openCreateDialog = () => {
    editingJudge.value = null;
    mutationHttp.name = '';
    mutationHttp.email = '';
    mutationHttp.password = '';
    mutationHttp.is_active = true;
    isDialogOpen.value = true;
};

const openEditDialog = (judge: User) => {
    editingJudge.value = judge;
    mutationHttp.name = judge.name;
    mutationHttp.email = judge.email;
    mutationHttp.password = '';
    mutationHttp.is_active = judge.is_active;
    isDialogOpen.value = true;
};

const saveJudge = async () => {
    try {
        if (editingJudge.value) {
            await mutationHttp.patch(admin.judges.update(editingJudge.value.id).url);
        } else {
            await mutationHttp.post(admin.judges.store().url);
        }

        isDialogOpen.value = false;
        fetchJudges();
    } catch (error) {
        console.error('Failed to save judge', error);
    }
};

const toggleStatus = async (judge: User) => {
    const toggleHttp = useHttp({ is_active: !judge.is_active });
    try {
        await toggleHttp.patch(admin.judges.update(judge.id).url);
        fetchJudges();
    } catch (error) {
        console.error('Failed to toggle status', error);
    }
};
</script>

<template>
    <Head title="Hakim" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Hakim" description="Urus hakim debat dan akses mereka." />
            <Button @click="openCreateDialog">
                <Plus class="w-4 h-4 mr-2" />
                Tambah Hakim
            </Button>
        </div>

        <div class="relative w-full overflow-auto rounded-xl border bg-background">
            <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Nama</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Email</th>
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
                            <tr v-else-if="judges.length === 0" class="border-b transition-colors">
                                <td colspan="4" class="p-8 text-center text-muted-foreground">
                                    Tiada hakim ditemui. Tambah hakim pertama anda untuk bermula.
                                </td>
                            </tr>
                            <tr v-for="judge in judges" :key="judge.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle font-medium">{{ judge.name }}</td>
                                <td class="p-4 align-middle">{{ judge.email }}</td>
                                <td class="p-4 align-middle">
                                    <Badge :variant="judge.is_active ? 'default' : 'secondary'">
                                        {{ judge.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="ghost" size="icon" @click="openEditDialog(judge)">
                                            <Edit2 class="w-4 h-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" @click="toggleStatus(judge)">
                                            <component :is="judge.is_active ? PowerOff : Power" class="w-4 h-4" />
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
                    <DialogTitle>{{ editingJudge ? 'Kemaskini Hakim' : 'Tambah Hakim' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingJudge ? 'Kemaskini maklumat hakim di bawah.' : 'Isi maklumat untuk mencipta hakim baharu.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="mutationHttp.name" placeholder="Nama Penuh" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="mutationHttp.email" type="email" placeholder="email@example.com" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">{{ editingJudge ? 'Kata Laluan Baharu (Pilihan)' : 'Kata Laluan' }}</Label>
                        <Input id="password" v-model="mutationHttp.password" type="password" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isDialogOpen = false">Batal</Button>
                    <Button @click="saveJudge">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
