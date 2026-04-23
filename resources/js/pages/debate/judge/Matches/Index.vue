<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useHttp } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { Swords, Eye, MapPin, Calendar, CheckCircle2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { unwrapCollection } from '@/lib/httpPayload';
import debate from '@/routes/debate';
import judge from '@/routes/judge';
import type { Match } from '@/types/debate';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Perlawanan Saya',
                href: debate.judge.matches.index().url,
            },
        ],
    },
});

const http = useHttp();
const matches = ref<Match[]>([]);
const loading = ref(true);
const page = usePage();
const userId = page.props.auth.user.id;

const fetchMatches = async () => {
    loading.value = true;

    try {
        const response = await http.get(judge.matches.index().url);
        matches.value = unwrapCollection<Match>(response);
    } catch (error) {
        matches.value = [];
        console.error('Failed to load matches', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchMatches);

const getAssignment = (match: Match) => {
    return match.judge_assignments?.find(a => a.judge_id === userId);
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'pending': return 'secondary';
        case 'in_progress': return 'default';
        case 'completed': return 'success';
        default: return 'outline';
    }
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'pending': return 'Belum Bermula';
        case 'in_progress': return 'Sedang Berjalan';
        case 'completed': return 'Selesai';
        default: return status;
    }
};
</script>

<template>
    <Head title="Perlawanan Saya" />

    <div class="p-6 space-y-6">
        <Heading title="Perlawanan Saya" description="Lihat dan urus perlawanan debat yang ditugaskan kepada anda." />

        <div v-if="loading" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="i in 3" :key="i" class="animate-pulse">
                <CardHeader class="pb-2">
                    <div class="h-4 w-20 bg-muted rounded mb-2"></div>
                    <div class="h-6 w-48 bg-muted rounded"></div>
                </CardHeader>
                <CardContent>
                    <div class="h-4 w-32 bg-muted rounded mt-4"></div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="matches.length === 0" class="flex flex-col items-center justify-center py-12 text-center border-2 border-dashed rounded-xl">
            <Swords class="w-12 h-12 text-muted-foreground/30 mb-4" />
            <h3 class="text-lg font-medium">Tiada perlawanan ditugaskan</h3>
            <p class="text-sm text-muted-foreground max-w-xs">Anda masih belum ditugaskan ke mana-mana perlawanan. Sila hubungi pentadbir.</p>
        </div>

        <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="match in matches" :key="match.id" class="overflow-hidden transition-all hover:ring-2 hover:ring-primary/20">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between mb-2">
                        <Badge variant="outline" class="text-[10px] uppercase">{{ match.round?.name }}</Badge>
                        <Badge :variant="getStatusVariant(match.status)">{{ statusLabel(match.status) }}</Badge>
                    </div>
                    <CardTitle class="text-lg">
                        <span class="text-primary">{{ match.government_team?.name }}</span>
                        <span class="mx-2 text-muted-foreground font-normal text-sm">lwn</span>
                        <span class="text-destructive">{{ match.opposition_team?.name }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center text-xs text-muted-foreground">
                            <MapPin class="w-3 h-3 mr-2" />
                            {{ match.room?.name }}
                        </div>
                        <div class="flex items-center text-xs text-muted-foreground">
                            <Calendar class="w-3 h-3 mr-2" />
                            {{ match.scheduled_at ? new Date(match.scheduled_at).toLocaleString() : 'Belum dijadualkan' }}
                        </div>
                    </div>

                    <div class="pt-4 border-t flex items-center justify-between">
                        <div class="flex gap-2">
                            <div v-if="getAssignment(match)?.checked_in_at" title="Daftar Hadir">
                                <CheckCircle2 class="w-5 h-5 text-green-500" />
                            </div>
                            <div v-if="getAssignment(match)?.submitted_at" title="Dihantar">
                                <CheckCircle2 class="w-5 h-5 text-blue-500" />
                            </div>
                        </div>
                        <Button size="sm" as-child>
                            <Link :href="debate.judge.matches.show(match.id)">
                                <Eye class="w-4 h-4 mr-2" />
                                Lihat Perlawanan
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
