<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login, dashboard } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Debat Piala Naib Canselor">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen bg-[#FAF9F6] text-[#1A1A1A] selection:bg-[#E5E1DA] selection:text-black dark:bg-[#0F0F0F] dark:text-[#EDEDED]">
        <!-- Subtle Dotted Grid Background -->
        <div class="fixed inset-0 pointer-events-none opacity-[0.03] dark:opacity-[0.05]" 
             style="background-image: radial-gradient(#000 1px, transparent 1px); background-size: 24px 24px;">
        </div>

        <div class="relative flex min-h-screen flex-col">
            <!-- Header -->
            <header class="border-b border-black/5 px-6 py-4 lg:px-12 dark:border-white/5">
                <div class="mx-auto flex max-w-7xl items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center border border-black/10 bg-white font-serif text-xl font-bold dark:border-white/10 dark:bg-white/5">
                            D
                        </div>
                        <span class="text-xs font-bold tracking-widest uppercase lg:text-sm">
                            DPNC <span class="text-black/40 dark:text-white/40">2026</span>
                        </span>
                    </div>
                    
                    <nav class="flex items-center gap-6">
                        <Link v-if="$page.props.auth.user" :href="dashboard()" class="text-sm font-medium hover:underline underline-offset-4">
                            Papan Pemuka
                        </Link>
                        <Link v-else :href="login()" class="group flex items-center gap-2 text-sm font-medium">
                            Log Masuk
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform group-hover:translate-x-0.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </Link>
                    </nav>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex grow flex-col items-center justify-center px-6 py-12 lg:px-12">
                <div class="mx-auto max-w-5xl text-center">
                    <!-- Hero Section -->
                    <div class="mb-12 space-y-6">
                        <div class="inline-block border border-black/10 px-3 py-1 text-[10px] font-bold tracking-[0.2em] uppercase dark:border-white/10">
                            Anjuran Kelab Debat Bahasa Melayu UMS
                        </div>
                        <h1 class="font-serif text-5xl font-light leading-tight tracking-tight lg:text-8xl">
                            Debat Piala <br />
                            <span class="italic text-[#800000] dark:text-[#FF4D4D]">Naib Canselor</span>
                        </h1>
                        <p class="mx-auto max-w-2xl text-lg leading-relaxed text-black/60 dark:text-white/60">
                            Platform intelektual ulung Universiti Malaysia Sabah, mempertemukan pemikir kritis dalam satu medan wacana yang berprestij.
                        </p>
                    </div>

                    <!-- Info Ribbon -->
                    <div class="mb-16 grid grid-cols-1 border border-black/10 bg-white/50 divide-y divide-black/10 sm:grid-cols-3 sm:divide-y-0 sm:divide-x dark:border-white/10 dark:bg-white/5 dark:divide-white/10">
                        <div class="px-8 py-6">
                            <span class="block text-[10px] font-bold tracking-widest uppercase text-black/40 dark:text-white/40">Format</span>
                            <span class="mt-1 block font-medium">Parlimen Asia</span>
                        </div>
                        <div class="px-8 py-6">
                            <span class="block text-[10px] font-bold tracking-widest uppercase text-black/40 dark:text-white/40">Lokasi</span>
                            <span class="mt-1 block font-medium">Kampus Induk, UMS</span>
                        </div>
                        <div class="px-8 py-6">
                            <span class="block text-[10px] font-bold tracking-widest uppercase text-black/40 dark:text-white/40">Tarikh</span>
                            <span class="mt-1 block font-medium">25-26 April 2026</span>
                        </div>
                    </div>

                    <!-- Portal Grid -->
                    <div class="grid grid-cols-1 gap-8 text-left lg:grid-cols-2">
                        <!-- Judge Card -->
                        <div class="group relative border border-black/10 bg-white p-10 transition-colors hover:bg-[#FDFCFB] dark:border-white/10 dark:bg-[#161616] dark:hover:bg-[#1A1A1A]">
                            <div class="mb-6 flex h-12 w-12 items-center justify-center border border-black/10 dark:border-white/10">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </div>
                            <h3 class="mb-3 font-serif text-2xl">Bilik Juri</h3>
                            <p class="mb-8 text-sm leading-relaxed text-black/60 dark:text-white/60">
                                Akses untuk panel hakim bagi menguruskan pemarkahan, penilaian pendebat, dan keputusan perlawanan secara digital.
                            </p>
                            <Link :href="login()" class="inline-flex items-center gap-2 border-b-2 border-[#800000] pb-1 text-sm font-bold uppercase tracking-widest text-[#800000] dark:border-[#FF4D4D] dark:text-[#FF4D4D]">
                                Masuk Portal Juri
                            </Link>
                        </div>

                        <!-- Tech/Admin Card -->
                        <div class="group relative border border-black/10 bg-white p-10 transition-colors hover:bg-[#FDFCFB] dark:border-white/10 dark:bg-[#161616] dark:hover:bg-[#1A1A1A]">
                            <div class="mb-6 flex h-12 w-12 items-center justify-center border border-black/10 dark:border-white/10">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <h3 class="mb-3 font-serif text-2xl">Bilik Gerakan</h3>
                            <p class="mb-8 text-sm leading-relaxed text-black/60 dark:text-white/60">
                                Kawalan teknikal untuk urusetia bagi pengurusan perlawanan, bilik debat, dan pendaftaran pasukan.
                            </p>
                            <Link :href="login()" class="inline-flex items-center gap-2 border-b-2 border-black pb-1 text-sm font-bold uppercase tracking-widest dark:border-white">
                                Masuk Portal Teknikal
                            </Link>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="border-t border-black/5 px-6 py-12 lg:px-12 dark:border-white/5">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 sm:flex-row">
                    <p class="text-xs font-medium text-black/40 dark:text-white/40">
                        &copy; 2026 Kelab Debat Bahasa Melayu UMS. Hak Cipta Terpelihara.
                    </p>
                    <div class="flex gap-8 text-[10px] font-bold tracking-widest uppercase text-black/40 dark:text-white/40">
                        <a href="#" class="hover:text-black dark:hover:text-white">Terma & Syarat</a>
                        <a href="#" class="hover:text-black dark:hover:text-white">Hubungi Kami</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>

<style>
body {
    font-family: 'Instrument Sans', sans-serif;
}

.font-serif {
    font-family: 'Playfair Display', serif;
}
</style>
