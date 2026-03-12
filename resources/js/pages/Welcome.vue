<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Package, AlertTriangle, Wrench, Bot, ClipboardList, Activity, TrendingUp, Layers, Moon, Sun, LogOut, ChevronRight } from 'lucide-vue-next';
import AiChat from '@/components/AiChat.vue';

type ColorType = 'blue' | 'orange' | 'green' | 'red' | 'black' | 'violet' | 'indigo' | 'emerald';

interface System {
    id: string; title: string; description: string; icon: string;
    color: ColorType; route: string; features: string[];
    stats?: { users: string; uptime: string };
}
interface Props { systems: System[] }
defineProps<Props>();

const isDark = ref(false);
onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});
const toggleDark = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

const logout = () => router.post('/logout');

const iconComponents: Record<string, any> = {
    Package, AlertTriangle, Wrench, Bot, ClipboardList, Activity, TrendingUp, Layers
};

const colorMap: Record<ColorType, { gradient: string; light: string; text: string; dot: string }> = {
    blue:    { gradient: 'from-blue-500 to-blue-600',    light: 'bg-blue-50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800/50',    text: 'text-blue-600 dark:text-blue-400',    dot: 'bg-blue-500' },
    orange:  { gradient: 'from-orange-500 to-orange-600', light: 'bg-orange-50 dark:bg-orange-900/20 border-orange-100 dark:border-orange-800/50', text: 'text-orange-600 dark:text-orange-400', dot: 'bg-orange-500' },
    green:   { gradient: 'from-green-500 to-emerald-600', light: 'bg-green-50 dark:bg-green-900/20 border-green-100 dark:border-green-800/50',   text: 'text-green-600 dark:text-green-400',   dot: 'bg-green-500' },
    red:     { gradient: 'from-red-500 to-red-600',      light: 'bg-red-50 dark:bg-red-900/20 border-red-100 dark:border-red-800/50',           text: 'text-red-600 dark:text-red-400',      dot: 'bg-red-500' },
    black:   { gradient: 'from-gray-700 to-gray-900',    light: 'bg-gray-50 dark:bg-gray-800/50 border-gray-100 dark:border-gray-700',          text: 'text-gray-700 dark:text-gray-300',    dot: 'bg-gray-600' },
    violet:  { gradient: 'from-violet-500 to-violet-600', light: 'bg-violet-50 dark:bg-violet-900/20 border-violet-100 dark:border-violet-800/50', text: 'text-violet-600 dark:text-violet-400', dot: 'bg-violet-500' },
    indigo:  { gradient: 'from-indigo-500 to-indigo-600', light: 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-100 dark:border-indigo-800/50', text: 'text-indigo-600 dark:text-indigo-400', dot: 'bg-indigo-500' },
    emerald: { gradient: 'from-emerald-500 to-teal-600', light: 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-100 dark:border-emerald-800/50', text: 'text-emerald-600 dark:text-emerald-400', dot: 'bg-emerald-500' },
};
</script>

<template>
    <Head title="Pilih Sistem" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 transition-colors duration-300">

        <header class="sticky top-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200/60 dark:border-gray-800/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">

                <div class="flex items-center gap-2.5">
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white leading-none">4W Department</p>
                        <p class="text-[10px] text-gray-400 leading-none mt-0.5">PT Dharma Polimetal</p>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <button @click="toggleDark"
                        class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-gray-500 dark:text-gray-400">
                        <Moon v-if="!isDark" class="w-4.5 h-4.5" />
                        <Sun v-else class="w-4.5 h-4.5" />
                    </button>
                    <button @click="logout"
                        class="flex items-center gap-1.5 h-9 px-3 rounded-xl text-xs font-semibold text-gray-500 dark:text-gray-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition-all duration-200 border border-transparent hover:border-red-100 dark:hover:border-red-900/50">
                        <LogOut class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Logout</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12">

            <div class="mb-8 sm:mb-10">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                    Selamat datang 👋
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Pilih sistem yang ingin diakses</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                <Link
                    v-for="system in systems" :key="system.id"
                    :href="system.route"
                    class="group relative bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 overflow-hidden flex flex-col"
                >
                    <div :class="`absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r ${colorMap[system.color].gradient} opacity-0 group-hover:opacity-100 transition-opacity duration-200`"></div>

                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-start justify-between mb-3.5">
                            <div :class="`w-10 h-10 rounded-xl bg-gradient-to-br ${colorMap[system.color].gradient} flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform duration-200`">
                                <component :is="iconComponents[system.icon]" class="w-5 h-5 text-white" />
                            </div>
                            <div :class="`flex items-center gap-1.5 px-2 py-1 rounded-lg text-[11px] font-semibold border ${colorMap[system.color].light} ${colorMap[system.color].text}`">
                                <span :class="`w-1.5 h-1.5 rounded-full ${colorMap[system.color].dot}`"></span>
                                Active
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-1 group-hover:text-gray-700 dark:group-hover:text-gray-100 transition-colors">
                            {{ system.title }}
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 leading-relaxed line-clamp-2 mb-3">
                            {{ system.description }}
                        </p>

                        <div class="space-y-1.5">
                            <div v-for="(feature, idx) in system.features.slice(0, 3)" :key="idx"
                                class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span :class="`w-1 h-1 rounded-full shrink-0 ${colorMap[system.color].dot}`"></span>
                                {{ feature }}
                            </div>
                            <div v-if="system.features.length > 3" :class="`text-xs font-medium pl-3 ${colorMap[system.color].text}`">
                                +{{ system.features.length - 3 }} fitur lainnya
                            </div>
                        </div>
                    </div>

                    <div class="px-4 sm:px-5 pb-4">
                        <div :class="`flex items-center justify-between w-full py-2.5 px-3.5 rounded-xl bg-gradient-to-r ${colorMap[system.color].gradient} text-white text-xs font-semibold shadow-sm group-hover:shadow-md transition-all duration-200`">
                            <span>Masuk ke sistem</span>
                            <ChevronRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform duration-200" />
                        </div>
                    </div>
                </Link>
            </div>

            <div class="mt-10 text-center">
                <p class="text-xs text-gray-300 dark:text-gray-700">© 2025 4W Department · PT Dharma Polimetal Tbk</p>
            </div>
        </main>
    </div>

    <AiChat />
</template>
