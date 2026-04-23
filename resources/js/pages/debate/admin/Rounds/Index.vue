<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { Plus, Edit2, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
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
import type { Round } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pusingan',
                href: admin.rounds.index().url,
            },
        ],
    },
});

const http = useHttp();
const mutationHttp = useHttp({
    name: '',
    sequence: null as number | null,
});
const rounds = ref<Round[]>([]);
const loading = ref(true);

const fetchRounds = async () => {
    loading.value = true;

    try {
        const response = await http.get(admin.rounds.index().url);
        rounds.value = unwrapCollection<Round>(response);
    } catch (error) {
        rounds.value = [];
        console.error('Failed to load rounds', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRounds);

// Create/Edit State
const isDialogOpen = ref(false);
const editingRound = ref<Round | null>(null);
const saving = ref(false);
const fieldErrors = ref<{
    name?: string;
    sequence?: string;
    general?: string;
}>({});

const nextSequence = (): number => {
    return rounds.value.reduce((maxSequence, round) => Math.max(maxSequence, round.sequence ?? 0), 0) + 1;
};

const openCreateDialog = () => {
    editingRound.value = null;
    fieldErrors.value = {};
    mutationHttp.name = '';
    mutationHttp.sequence = nextSequence();
    isDialogOpen.value = true;
};

const openEditDialog = (round: Round) => {
    editingRound.value = round;
    fieldErrors.value = {};
    mutationHttp.name = round.name;
    mutationHttp.sequence = round.sequence;
    isDialogOpen.value = true;
};

const updateRoundName = (value: string | number): void => {
    mutationHttp.name = String(value ?? '');

    if (fieldErrors.value.name) {
        fieldErrors.value.name = undefined;
    }

    if (fieldErrors.value.general) {
        fieldErrors.value.general = undefined;
    }
};

const updateSequence = (value: string | number): void => {
    const normalized = String(value ?? '').trim();
    mutationHttp.sequence = normalized === '' ? null : Number(normalized);

    if (fieldErrors.value.sequence) {
        fieldErrors.value.sequence = undefined;
    }

    if (fieldErrors.value.general) {
        fieldErrors.value.general = undefined;
    }
};

const saveRound = async () => {
    saving.value = true;
    fieldErrors.value = {};

    try {
        mutationHttp.name = mutationHttp.name.trim();

        if (editingRound.value) {
            await mutationHttp.patch(admin.rounds.update(editingRound.value.id).url);
        } else {
            await mutationHttp.post(admin.rounds.store().url);
        }

        isDialogOpen.value = false;
        await fetchRounds();
        toast.success(editingRound.value ? 'Pusingan berjaya dikemas kini.' : 'Pusingan berjaya dicipta.');
    } catch (error) {
        const maybeError = error as {
            response?: {
                status?: number;
                data?: string | {
                    errors?: Record<string, string[] | string>;
                    message?: string;
                };
            };
            message?: string;
        };

        let responseData: {
            errors?: Record<string, string[] | string>;
            message?: string;
        } | null = null;

        if (typeof maybeError.response?.data === 'string') {
            try {
                responseData = JSON.parse(maybeError.response.data) as {
                    errors?: Record<string, string[] | string>;
                    message?: string;
                };
            } catch {
                responseData = null;
            }
        } else if (maybeError.response?.data) {
            responseData = maybeError.response.data;
        }

        const errors = responseData?.errors;

        if (maybeError.response?.status === 422 && errors) {
            fieldErrors.value = {
                name: Array.isArray(errors.name) ? errors.name[0] : errors.name,
                sequence: Array.isArray(errors.sequence) ? errors.sequence[0] : errors.sequence,
            };

            const messages = Object.values(errors)
                .flatMap((value) => Array.isArray(value) ? value : [value])
                .filter((value): value is string => Boolean(value));

            const firstMessage = messages[0] ?? 'Pengesahan gagal. Sila semak input anda.';
            fieldErrors.value.general = firstMessage;
            toast.error(firstMessage);
        } else {
            const generalMessage = responseData?.message ?? maybeError.message ?? 'Gagal menyimpan pusingan. Sila cuba lagi.';

            fieldErrors.value = {
                general: generalMessage,
            };

            toast.error(generalMessage);
        }

        console.error('Failed to save round', error);
    } finally {
        saving.value = false;
    }
};

const deleteRound = async (round: Round) => {
    if (!confirm('Adakah anda pasti mahu memadam pusingan ini?')) {
        return;
    }

    try {
        await http.delete(admin.rounds.destroy(round.id).url);
        fetchRounds();
    } catch (error) {
        console.error('Failed to delete round', error);
    }
};
</script>

<template>
    <Head title="Pusingan" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Pusingan" description="Urus pusingan kejohanan dan turutan setiap pusingan." />
            <Button @click="openCreateDialog">
                <Plus class="w-4 h-4 mr-2" />
                Tambah Pusingan
            </Button>
        </div>

        <div class="relative w-full overflow-auto rounded-xl border bg-background">
            <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground w-20">Turutan</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Nama</th>
                                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <template v-if="loading">
                                <tr v-for="i in 3" :key="i" class="border-b transition-colors">
                                    <td class="p-4 align-middle"><div class="h-4 w-8 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-32 animate-pulse rounded bg-muted"></div></td>
                                    <td class="p-4 align-middle"><div class="h-4 w-12 animate-pulse rounded bg-muted ml-auto"></div></td>
                                </tr>
                            </template>
                            <tr v-else-if="rounds.length === 0" class="border-b transition-colors">
                                <td colspan="3" class="p-8 text-center text-muted-foreground">
                                    Tiada pusingan ditemui. Cipta pusingan pertama anda.
                                </td>
                            </tr>
                            <tr v-for="round in rounds" :key="round.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle font-medium">{{ round.sequence }}</td>
                                <td class="p-4 align-middle font-medium">{{ round.name }}</td>
                                <td class="p-4 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="ghost" size="icon" @click="openEditDialog(round)">
                                            <Edit2 class="w-4 h-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" @click="deleteRound(round)">
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
                    <DialogTitle>{{ editingRound ? 'Kemaskini Pusingan' : 'Tambah Pusingan' }}</DialogTitle>
                    <DialogDescription>
                        Tetapkan nama pusingan dan kedudukannya dalam kejohanan.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <p v-if="fieldErrors.general" class="text-sm text-destructive">{{ fieldErrors.general }}</p>

                    <div class="grid gap-2">
                        <Label for="name">Nama Pusingan</Label>
                        <Input
                            id="name"
                            :model-value="mutationHttp.name"
                            placeholder="Pusingan 1"
                            @update:model-value="updateRoundName"
                        />
                        <p v-if="fieldErrors.name" class="text-xs text-destructive">{{ fieldErrors.name }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sequence">Turutan</Label>
                        <Input
                            id="sequence"
                            :model-value="mutationHttp.sequence ?? ''"
                            type="number"
                            @update:model-value="updateSequence"
                        />
                        <p class="text-xs text-muted-foreground">Nombor lebih kecil akan dipaparkan lebih awal (1 = Pusingan 1, 2 = Pusingan 2).</p>
                        <p v-if="fieldErrors.sequence" class="text-xs text-destructive">{{ fieldErrors.sequence }}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isDialogOpen = false">Batal</Button>
                    <Button @click="saveRound" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
