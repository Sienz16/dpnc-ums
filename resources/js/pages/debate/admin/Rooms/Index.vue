<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Plus, Edit2, Trash2, Power, PowerOff } from 'lucide-vue-next';
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
import type { Room } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Bilik',
                href: admin.rooms.index().url,
            },
        ],
    },
});

const http = useHttp();
const mutationHttp = useHttp({
    name: '',
    location: '',
    is_active: true,
});
const rooms = ref<Room[]>([]);
const loading = ref(true);

const fetchRooms = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.rooms.index().url);
        rooms.value = unwrapCollection<Room>(response);
    } catch (error) {
        rooms.value = [];
        console.error('Failed to load rooms', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRooms);

// Create/Edit State
const isDialogOpen = ref(false);
const editingRoom = ref<Room | null>(null);

const openCreateDialog = () => {
    editingRoom.value = null;
    mutationHttp.name = '';
    mutationHttp.location = '';
    mutationHttp.is_active = true;
    isDialogOpen.value = true;
};

const openEditDialog = (room: Room) => {
    editingRoom.value = room;
    mutationHttp.name = room.name;
    mutationHttp.location = room.location || '';
    mutationHttp.is_active = room.is_active;
    isDialogOpen.value = true;
};

const saveRoom = async () => {
    try {
        if (editingRoom.value) {
            await mutationHttp.patch(admin.rooms.update(editingRoom.value.id).url);
        } else {
            await mutationHttp.post(admin.rooms.store().url);
        }

        isDialogOpen.value = false;
        fetchRooms();
    } catch (error) {
        console.error('Failed to save room', error);
    }
};

const deleteRoom = async (room: Room) => {
    if (!confirm('Adakah anda pasti mahu memadam bilik ini?')) {
        return;
    }

    try {
        await http.delete(admin.rooms.destroy(room.id).url);
        fetchRooms();
    } catch (error) {
        console.error('Failed to delete room', error);
    }
};

const toggleStatus = async (room: Room) => {
    const toggleHttp = useHttp({ is_active: !room.is_active });
    try {
        await toggleHttp.patch(admin.rooms.update(room.id).url);
        fetchRooms();
    } catch (error) {
        console.error('Failed to toggle status', error);
    }
};
</script>

<template>
    <Head title="Bilik" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Bilik" description="Urus bilik debat dan lokasi setiap bilik." />
            <Button @click="openCreateDialog">
                <Plus class="w-4 h-4 mr-2" />
                Tambah Bilik
            </Button>
        </div>

        <div class="relative w-full overflow-auto rounded-xl border bg-background">
            <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Nama</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Lokasi</th>
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
                            <tr v-else-if="rooms.length === 0" class="border-b transition-colors">
                                <td colspan="4" class="p-8 text-center text-muted-foreground">
                                    Tiada bilik ditemui. Tambah bilik pertama anda.
                                </td>
                            </tr>
                            <tr v-for="room in rooms" :key="room.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle font-medium">{{ room.name }}</td>
                                <td class="p-4 align-middle">{{ room.location || '-' }}</td>
                                <td class="p-4 align-middle">
                                    <Badge :variant="room.is_active ? 'default' : 'secondary'">
                                        {{ room.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="ghost" size="icon" @click="openEditDialog(room)">
                                            <Edit2 class="w-4 h-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" @click="toggleStatus(room)">
                                            <component :is="room.is_active ? PowerOff : Power" class="w-4 h-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" @click="deleteRoom(room)">
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
                    <DialogTitle>{{ editingRoom ? 'Kemaskini Bilik' : 'Tambah Bilik' }}</DialogTitle>
                    <DialogDescription>
                        Tetapkan nama bilik dan lokasi fizikalnya.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name">Nama Bilik</Label>
                        <Input id="name" v-model="mutationHttp.name" placeholder="Sidang 1" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="location">Lokasi</Label>
                        <Input id="location" v-model="mutationHttp.location" placeholder="Bilik Seminar 1" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isDialogOpen = false">Batal</Button>
                    <Button @click="saveRoom">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
